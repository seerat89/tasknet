import { type TaskInput, ToolError } from '../api';
import { h } from '../dom';
import { quoted } from '../format';
import { PRIORITY_LABELS, STATUS_LABELS } from '../rules';
import type { Task } from '../types';
import { button, type Choices, priorityLevelChoices, select, statusChoices } from '../ui';
import { messageOf, recordingAttempt, type ViewContext } from './view';

type Choice = 'status' | 'priority';

interface Draft {
    /** The saved values the choice was made against. */
    from: Pick<Task, Choice>;
    /** Only the fields that differ from them. */
    input: Pick<TaskInput, Choice>;
}

/** Status and priority choices not yet saved, by task ID. Views keep them across redraws. */
export type Drafts = Map<number, Draft>;

export const UNSAVED_HINT = 'Not saved yet. Press Enter or choose Save.';

export const STALE_DRAFT = 'An unsaved choice was cleared because its task changed.';

/**
 * Forget choices made against values that have since changed, so saving never silently overwrites
 * something newer. Tasks being saved are left alone. Returns whether anything was forgotten.
 */
export function dropStaleDrafts(drafts: Drafts, tasks: Task[], saving: ReadonlySet<number>): boolean {
    let dropped = false;

    for (const [id, draft] of drafts) {
        const task = tasks.find((candidate) => candidate.id === id);
        const changed = !task || (Object.keys(draft.input) as Choice[]).some((field) => task[field] !== draft.from[field]);

        if (changed && !saving.has(id)) {
            drafts.delete(id);
            dropped = true;
        }
    }

    return dropped;
}

/**
 * Status, priority and complete/reopen controls, shared by the task list and task detail.
 *
 * Choosing from a list never saves, however it was done: it shows Save and Discard, and Enter on the
 * changed list saves as well. Complete and Reopen are explicit actions and save at once.
 */
export function taskControls(
    task: Task,
    busy: boolean,
    drafts: Drafts,
    change: (input: TaskInput) => void,
    announce: (message: string) => void,
): HTMLElement {
    const done = task.status === 'completed';
    const hint = h('span', { class: 'sr-only', id: `tn-unsaved-${task.id}` }, UNSAVED_HINT);
    const drafted = (field: Choice): boolean => drafts.get(task.id)?.input[field] !== undefined;

    function choiceList<K extends Choice>(field: K, label: string, choices: Choices<Task[K]>): HTMLSelectElement {
        const shown = (drafts.get(task.id)?.input[field] as Task[K] | undefined) ?? task[field];
        const element: HTMLSelectElement = select<Task[K]>(
            label,
            shown,
            choices,
            (value) => {
                if (busy) {
                    element.value = shown;

                    return;
                }

                const before = drafts.get(task.id);
                const input: Draft['input'] = { ...before?.input, [field]: value };

                if (value === task[field]) {
                    delete input[field];
                }

                if (Object.keys(input).length === 0) {
                    drafts.delete(task.id);
                } else {
                    drafts.set(task.id, { from: { status: task.status, priority: task.priority }, input });

                    if (!before) {
                        announce(UNSAVED_HINT);
                    }
                }

                // Only these attributes change: redrawing from inside a change event is not safe.
                reflect();
            },
            { key: `${field}-${task.id}` },
        );

        if (busy) {
            element.setAttribute('aria-disabled', 'true');
        }

        element.addEventListener('keydown', (event) => {
            if (drafted(field) && (event.key === 'Enter' || event.key === 'Escape')) {
                // Enter would otherwise open the list.
                event.preventDefault();
                (event.key === 'Enter' ? confirm : discard)();
            }
        });

        return element;
    }

    const lists = {
        status: choiceList('status', `Status of ${task.title}`, statusChoices),
        priority: choiceList('priority', `Priority of ${task.title}`, priorityLevelChoices),
    };
    const saveButton = button(busy ? 'Saving…' : 'Save', () => confirm(), {
        primary: true,
        disabled: busy,
        key: `save-${task.id}`,
        ariaLabel: `${busy ? 'Saving' : 'Save'} changes to ${task.title}`,
    });
    const discardButton = button('Discard', () => discard(), {
        disabled: busy,
        key: `discard-${task.id}`,
        ariaLabel: `Discard changes to ${task.title}`,
    });

    /** Both buttons go away afterwards, so focus on one of them moves to the list that was changed. */
    function keepFocus(): void {
        if (document.activeElement === saveButton || document.activeElement === discardButton) {
            (drafted('status') ? lists.status : lists.priority).focus();
        }
    }

    /** Save the chosen values, and nothing else. */
    function confirm(): void {
        const draft = drafts.get(task.id);

        if (!busy && draft) {
            keepFocus();
            change(draft.input);
        }
    }

    function discard(): void {
        if (!busy && drafts.has(task.id)) {
            keepFocus();
            drafts.delete(task.id);
            lists.status.value = task.status;
            lists.priority.value = task.priority;
            reflect();
            announce('Change discarded.');
        }
    }

    /** Match the buttons and descriptions to whether there is something to save. */
    function reflect(): void {
        saveButton.hidden = discardButton.hidden = !drafts.has(task.id);

        for (const field of ['status', 'priority'] as const) {
            if (drafted(field)) {
                lists[field].setAttribute('aria-describedby', hint.id);
            } else {
                lists[field].removeAttribute('aria-describedby');
            }
        }
    }

    reflect();

    return h(
        'div',
        { class: 'tn-controls', 'aria-busy': busy ? 'true' : undefined },
        button(
            done ? 'Reopen' : 'Complete',
            () => {
                if (!busy) {
                    // An explicit action of its own: any unsaved choice on this task is dropped, not sent.
                    drafts.delete(task.id);
                    change({ status: done ? 'todo' : 'completed' });
                }
            },
            { disabled: busy, key: `toggle-${task.id}`, ariaLabel: `${done ? 'Reopen' : 'Complete'} ${task.title}` },
        ),
        lists.status,
        lists.priority,
        saveButton,
        discardButton,
        hint,
        busy && !drafts.has(task.id) && h('span', { class: 'tn-muted' }, 'Saving…'),
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
