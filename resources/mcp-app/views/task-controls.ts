import { type TaskInput, ToolError } from '../api';
import { h } from '../dom';
import { quoted } from '../format';
import { PRIORITY_LABELS, STATUS_LABELS } from '../rules';
import type { Task } from '../types';
import { button, type Choices, priorityLevelChoices, select, statusChoices } from '../ui';
import { messageOf, recordingAttempt, type ViewContext } from './view';

type Choice = 'status' | 'priority';

/** Choices made with the keyboard but not yet saved, by task ID. Views keep them across redraws. */
export type Drafts = Map<number, Pick<TaskInput, Choice>>;

// Keys that move through a closed select, changing its value on the way.
const BROWSING_KEY = /^(Arrow(Up|Down|Left|Right)|Home|End|Page(Up|Down)|\S)$/;

/**
 * A select that saves a pointer choice at once. A value reached by moving through the options with the
 * keyboard is saved only on Enter: Escape, or leaving the select, puts the saved value back.
 *
 * Leaving never saves, so that no save can start in the middle of whatever the user does next.
 */
function savingSelect<K extends Choice>(config: {
    label: string;
    task: Task;
    field: K;
    choices: Choices<Task[K]>;
    busy: boolean;
    drafts: Drafts;
    send: (input: TaskInput) => void;
    /** Called when an unsaved choice is dropped because the user moved on. */
    abandoned: () => void;
}): HTMLElement {
    const { task, field, drafts, busy } = config;
    const saved = task[field];
    const key = `${field}-${task.id}`;
    // Positioned over the content (see .tn-hint), so showing or hiding it never moves what is being clicked.
    const hint = h('span', { class: 'tn-hint', id: `tn-hint-${key}` }, 'Press Enter to save');
    const drafted = (): string | undefined => drafts.get(task.id)?.[field];
    const discard = (): void => {
        const draft = drafts.get(task.id);

        delete draft?.[field];

        if (draft && Object.keys(draft).length === 0) {
            drafts.delete(task.id);
        }

        hint.hidden = true;
    };
    let browsing = false;

    const element = select<Task[K]>(
        config.label,
        (drafted() as Task[K] | undefined) ?? saved,
        config.choices,
        (value) => {
            if (busy) {
                element.value = saved;
            } else if (value === saved) {
                // Back where it started: nothing is left to save.
                discard();
            } else if (browsing) {
                drafts.set(task.id, { ...drafts.get(task.id), [field]: value });
                hint.hidden = false;
            } else {
                config.send({ [field]: value });
            }
        },
        { key },
    );

    hint.hidden = drafted() === undefined;
    element.setAttribute('aria-describedby', hint.id);

    if (busy) {
        element.setAttribute('aria-disabled', 'true');
    }

    element.addEventListener('pointerdown', () => (browsing = false));
    element.addEventListener('keydown', (event) => {
        const value = drafted();

        if (event.key === 'Enter' && value !== undefined) {
            event.preventDefault();
            config.send({ [field]: value });
        } else if (event.key === 'Escape' && value !== undefined) {
            event.preventDefault();
            discard();
            element.value = saved;
        }

        // Alt+arrow opens the list instead; choosing from an open list is a deliberate choice.
        browsing = !event.altKey && !event.ctrlKey && !event.metaKey && BROWSING_KEY.test(event.key);
    });
    element.addEventListener('blur', () => {
        // A redraw puts focus on the replacement select, which keeps the draft: that is not leaving.
        setTimeout(() => {
            const focused = (document.activeElement as HTMLElement | null)?.dataset.key;

            if (drafted() !== undefined && focused !== key) {
                discard();
                element.value = saved;
                config.abandoned();
            }
        }, 0);
    });

    return h('span', { class: 'tn-saving-select' }, element, hint);
}

/** Status, priority and complete/reopen controls, shared by the task list and task detail. */
export function taskControls(
    task: Task,
    busy: boolean,
    drafts: Drafts,
    change: (input: TaskInput) => void,
    abandoned: () => void,
): HTMLElement {
    const done = task.status === 'completed';
    // A save sends only what the user just confirmed; any other unsaved choice on this task is dropped.
    const send = (input: TaskInput): void => {
        if (!busy) {
            drafts.delete(task.id);
            change(input);
        }
    };
    const shared = { task, busy, drafts, send, abandoned };

    return h(
        'div',
        { class: 'tn-controls', 'aria-busy': busy ? 'true' : undefined },
        button(done ? 'Reopen' : 'Complete', () => send({ status: done ? 'todo' : 'completed' }), {
            disabled: busy,
            key: `toggle-${task.id}`,
            ariaLabel: `${done ? 'Reopen' : 'Complete'} ${task.title}`,
        }),
        savingSelect({ ...shared, label: `Status of ${task.title}`, field: 'status', choices: statusChoices }),
        savingSelect({ ...shared, label: `Priority of ${task.title}`, field: 'priority', choices: priorityLevelChoices }),
        busy && h('span', { class: 'tn-muted' }, 'Saving…'),
    );
}

export type SaveOutcome =
    /** TaskNet returned the saved task. */
    | { kind: 'saved'; task: Task }
    /** TaskNet rejected the change. `reload` is set when the screen is probably out of date. */
    | { kind: 'refused'; message: string; reload: boolean }
    /** Nothing says whether the change happened. */
    | { kind: 'unconfirmed'; message: string };

export const UNCONFIRMED_SHOWN = 'The change could not be confirmed. The current values are shown.';

export async function saveTask(context: ViewContext, task: Task, input: TaskInput): Promise<SaveOutcome> {
    const name = `task #${task.id} ${quoted(task.title)} in project #${task.project_id}`;

    try {
        const saved = await recordingAttempt(context, `change ${name}`, () =>
            context.api.updateTask(task.project_id, task.id, input),
        );

        context.recordChange(
            `Changed ${name}: status ${STATUS_LABELS[saved.status]}, priority ${PRIORITY_LABELS[saved.priority]}.`,
        );

        return { kind: 'saved', task: saved };
    } catch (error) {
        if (error instanceof ToolError && error.kind === 'refused') {
            // A refusal without a field problem means the task or access is gone.
            return { kind: 'refused', message: messageOf(error), reload: Object.keys(error.fieldErrors).length === 0 };
        }

        return { kind: 'unconfirmed', message: messageOf(error) };
    }
}
