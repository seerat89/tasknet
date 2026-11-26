import { ToolError } from './api';
import { h } from './dom';
import { alert, button } from './ui';
import { messageOf } from './views/view';

export interface Field {
    name: string;
    element: HTMLElement;
    control: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;
    /** The trimmed value, matching how the TaskNet website treats input. */
    value(): string;
    /** The first client-side problem. The server validates again and has the final say. */
    problem(): string | undefined;
    setError(message: string | undefined): void;
}

function field(name: string, label: string, control: Field['control'], problem: (value: string) => string | undefined): Field {
    const errorId = `tn-${name}-error`;
    const error = h('p', { class: 'tn-field-error', id: errorId, hidden: true });
    const value = (): string => control.value.trim();

    control.id = `tn-${name}`;
    control.setAttribute('name', name);

    const result: Field = {
        name,
        control,
        element: h('div', { class: 'tn-field' }, h('label', { for: control.id }, label), control, error),
        value,
        problem: () => problem(value()),
        setError(message) {
            error.textContent = message ?? '';
            error.hidden = message === undefined;

            if (message === undefined) {
                control.removeAttribute('aria-invalid');
                control.removeAttribute('aria-describedby');
            } else {
                control.setAttribute('aria-invalid', 'true');
                control.setAttribute('aria-describedby', errorId);
            }
        },
    };

    control.addEventListener('input', () => result.setError(undefined));
    control.addEventListener('change', () => result.setError(undefined));

    return result;
}

export function textField(config: {
    name: string;
    label: string;
    value?: string;
    max: number;
    required?: boolean;
    multiline?: boolean;
}): Field {
    const control = config.multiline
        ? h('textarea', { class: 'tn-input', rows: 4 })
        : h('input', { class: 'tn-input', type: 'text', autocomplete: 'off' });

    control.value = config.value ?? '';
    control.required = config.required ?? false;

    const subject = config.label.replace(/ \(optional\)$/, '').toLowerCase();

    return field(config.name, config.label, control, (value) => {
        if (config.required && value === '') {
            return `The ${subject} is required.`;
        }

        // Count characters as Laravel does, so an emoji counts once.
        if ([...value].length > config.max) {
            return `The ${subject} may not be longer than ${config.max} characters.`;
        }

        return undefined;
    });
}

export function selectField(config: {
    name: string;
    label: string;
    value: string;
    choices: ReadonlyArray<readonly [string, string]>;
}): Field {
    const control = h('select', { class: 'tn-select' }, ...config.choices.map(([value, text]) => h('option', { value }, text)));

    control.value = config.value;

    return field(config.name, config.label, control, (value) =>
        config.choices.some(([choice]) => choice !== '' && choice === value) ? undefined : `Choose a ${config.label.toLowerCase()}.`,
    );
}

export function dateField(config: { name: string; label: string; value?: string }): Field {
    const control = h('input', { class: 'tn-input', type: 'date' });

    control.value = config.value ?? '';

    return field(config.name, config.label, control, (value) =>
        control.validity?.badInput || (value !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(value)) ? 'Enter a complete, valid date.' : undefined,
    );
}

type Values = Record<string, string>;

export interface CheckResult {
    message: string;
    /** What to tell the model, if this answer is still the one the user is waiting for. */
    note: string;
    /** The matching record, when one was found. */
    open?: { label: string; go: () => void };
}

/**
 * A form that allows one submission at a time.
 *
 * `save` should navigate away on success; the form stays locked so it cannot be sent twice.
 *
 * Duplicate protection lives only here: the server has no idempotency key, and a conversation that is
 * reopened shows a fresh form. This is an accepted limitation.
 */
export function createForm(config: {
    fields: Field[];
    submitLabel: string;
    cancel: () => void;
    announce: (message: string, urgent?: boolean) => void;
    /**
     * `changes` holds the fields to send when editing: those the user altered, plus any sent in an earlier
     * attempt whose outcome is unknown.
     */
    save: (values: Values, changes: Values) => Promise<void>;
    /**
     * For creations, where repeating an unconfirmed request could add a duplicate. `check` looks for the
     * record without leaving the form, so nothing typed is lost.
     */
    duplicateRisk?: { check: (values: Values) => Promise<CheckResult>; record: (note: string) => unknown };
}): HTMLFormElement {
    let locked = false;
    let attempts = 0;

    const read = (): Values => Object.fromEntries(config.fields.map((item) => [item.name, item.value()]));
    // Read back from the controls, which normalise line breaks, so untouched text never counts as a change.
    const original = read();
    const unsettled = new Set<string>();

    const feedback = h('div', {});
    const fieldset = h('fieldset', { class: 'grid gap-3' }, ...config.fields.map((item) => item.element));
    const submit = button(config.submitLabel, () => void send(), { primary: true, key: 'submit' });
    const cancel = button('Cancel', config.cancel);
    const form = h('form', { class: 'grid gap-3', novalidate: true }, feedback, fieldset, h('div', { class: 'tn-actions' }, submit, cancel));

    const setDisabled = (element: HTMLElement, disabled: boolean): void => {
        if (disabled) {
            element.setAttribute('aria-disabled', 'true');
        } else {
            element.removeAttribute('aria-disabled');
        }
    };

    const setBusy = (busy: boolean): void => {
        form.setAttribute('aria-busy', String(busy));
        fieldset.disabled = busy;
        setDisabled(submit, busy);
        setDisabled(cancel, busy);
        submit.textContent = busy ? 'Saving…' : config.submitLabel;
    };

    /** Show a form-level message, say it, and put the focus where the user can act on it. */
    const report = (message: string, actions: HTMLElement[] = [], focus?: HTMLElement): void => {
        const box = alert(message, ...actions);

        feedback.replaceChildren(box);
        config.announce(message, true);
        (focus ?? actions[0] ?? box).focus();
    };

    async function send(): Promise<void> {
        if (locked) {
            return;
        }

        feedback.replaceChildren();

        const invalid = config.fields.filter((item) => {
            const problem = item.problem();

            item.setError(problem);

            return problem !== undefined;
        });

        if (invalid.length > 0) {
            report('Check the highlighted fields.', [], invalid[0].control);

            return;
        }

        locked = true;
        attempts++;

        const values = read();
        const changes = Object.fromEntries(
            Object.entries(values).filter(([name, value]) => unsettled.has(name) || value !== original[name]),
        );

        setBusy(true);
        config.announce('Saving…');

        try {
            await config.save(values, changes);
        } catch (error) {
            setBusy(false);
            showFailure(error, values, changes);
        }
    }

    function showFailure(error: unknown, values: Values, changes: Values): void {
        const refused = error instanceof ToolError && error.kind === 'refused';

        if (!refused) {
            Object.keys(changes).forEach((name) => unsettled.add(name));
        }

        if (config.duplicateRisk && !refused) {
            holdForCheck(`${messageOf(error)} It may or may not have been saved, so check before sending it again.`, values);

            return;
        }

        locked = false;

        const fieldErrors = error instanceof ToolError ? error.fieldErrors : {};
        const matched = config.fields.filter((item) => fieldErrors[item.name] !== undefined);

        matched.forEach((item) => item.setError(fieldErrors[item.name]));
        report(messageOf(error), [], matched[0]?.control);
    }

    /** Let the form be sent again. Any check still running is no longer wanted. */
    const unlock = (): void => {
        attempts++;
        locked = false;
        setDisabled(submit, false);
    };

    const sendAgain = (): HTMLButtonElement =>
        button('Allow sending again', () => {
            unlock();
            feedback.replaceChildren();
            submit.focus();
        });

    /** Stay locked until the user decides: the record may already exist. */
    function holdForCheck(message: string, values: Values): void {
        let checking = false;
        const check = button('Check whether it was saved', () => void look(), { key: 'check' });

        async function look(): Promise<void> {
            if (checking) {
                return;
            }

            checking = true;
            setDisabled(check, true);
            config.announce('Checking…');

            const attempt = attempts;
            let result: CheckResult;

            try {
                result = await config.duplicateRisk!.check(values);
            } catch (error) {
                if (attempt === attempts) {
                    holdForCheck(`Could not check. ${messageOf(error)}`, values);
                }

                return;
            }

            // The user did not wait: this answer must not change the form or move their focus.
            if (attempt !== attempts) {
                return;
            }

            config.duplicateRisk!.record(result.note);

            if (result.open) {
                report(result.message, [button(result.open.label, result.open.go), sendAgain()]);
            } else {
                unlock();
                report(result.message, [], submit);
            }
        }

        locked = true;
        setDisabled(submit, true);
        report(message, [check, sendAgain()]);
    }

    // Native submission may be blocked by the sandbox and must never navigate the frame.
    form.addEventListener('submit', (event) => event.preventDefault());
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target instanceof HTMLInputElement && !event.isComposing) {
            event.preventDefault();
            void send();
        }
    });

    return form;
}
