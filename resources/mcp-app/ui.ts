import { h } from './dom';
import { formatDate, isOverdue } from './format';
import { PRIORITIES, PRIORITY_LABELS, STATUS_LABELS, STATUSES } from './rules';
import type { Priority, Task } from './types';

/**
 * A disabled button stays focusable and ignores clicks (`aria-disabled`), so keyboard users are not
 * thrown to the top of the page while something is being saved.
 */
export function button(
    label: string,
    onClick: () => void,
    config: { primary?: boolean; link?: boolean; disabled?: boolean; key?: string; ariaLabel?: string } = {},
): HTMLButtonElement {
    // type="button" throughout: sandboxed hosts may block native form submission.
    const element = h(
        'button',
        {
            type: 'button',
            class: config.link ? 'tn-link' : config.primary ? 'tn-button tn-button-primary' : 'tn-button',
            'aria-disabled': config.disabled ? 'true' : undefined,
            'data-key': config.key ?? label,
            'aria-label': config.ariaLabel,
        },
        label,
    );

    element.addEventListener('click', () => {
        if (element.getAttribute('aria-disabled') !== 'true') {
            onClick();
        }
    });

    return element;
}

export function header(
    title: string,
    config: { back?: { label: string; go: () => void; disabled?: boolean }; subtitle?: string; actions?: Node[] } = {},
): HTMLElement {
    return h(
        'header',
        { class: 'tn-header' },
        config.back && button(`← ${config.back.label}`, config.back.go, { link: true, key: 'back', disabled: config.back.disabled }),
        h(
            'div',
            { class: 'tn-header-row' },
            h(
                'div',
                { class: 'min-w-0' },
                // Keyed so that focus placed on the heading also survives a redraw.
                h('h1', { class: 'tn-title', tabindex: -1, 'data-key': 'heading' }, title),
                config.subtitle !== undefined && h('p', { class: 'tn-muted' }, config.subtitle),
            ),
            config.actions && h('div', { class: 'tn-actions' }, ...config.actions),
        ),
    );
}

// None of the blocks below is a live region: views are redrawn often, which would repeat or swallow
// announcements. Messages are spoken through the controller's persistent regions (ViewContext.announce).

export function loading(label = 'Loading…'): HTMLElement {
    return h('div', { class: 'tn-state' }, h('span', { class: 'tn-spinner', 'aria-hidden': 'true' }), label);
}

export function alert(message: string, ...actions: Node[]): HTMLElement {
    return h(
        'div',
        { class: 'tn-alert', tabindex: -1 },
        h('p', {}, message),
        actions.length > 0 && h('div', { class: 'tn-actions' }, ...actions),
    );
}

export function notice(message: string): HTMLElement {
    return h('p', { class: 'tn-notice' }, message);
}

export function emptyState(title: string, text: string, ...actions: Node[]): HTMLElement {
    return h(
        'div',
        { class: 'tn-empty' },
        h('h2', { class: 'font-medium' }, title),
        h('p', { class: 'tn-muted' }, text),
        actions.length > 0 && h('div', { class: 'tn-actions justify-center' }, ...actions),
    );
}

export function progress(done: number, total: number): HTMLElement {
    const percent = total === 0 ? 0 : Math.round((done / total) * 100);

    return h(
        'div',
        { class: 'grid gap-1' },
        h('p', { class: 'tn-muted' }, total === 0 ? 'No tasks yet' : `${done} of ${total} completed (${percent}%)`),
        h('progress', { class: 'tn-progress', max: 100, value: percent, 'aria-label': 'Task completion' }),
    );
}

export function dueDate(task: Task, currentDate: string, locale?: string): HTMLElement {
    if (task.due_date === null) {
        return h('span', { class: 'tn-muted' }, 'No due date');
    }

    const overdue = isOverdue(task, currentDate);

    return h('span', { class: overdue ? 'tn-overdue' : 'tn-muted' }, `${overdue ? 'Overdue: ' : 'Due '}${formatDate(task.due_date, locale)}`);
}

export type Choices<T extends string> = ReadonlyArray<readonly [T, string]>;

export function select<T extends string>(
    label: string,
    value: T,
    choices: Choices<T>,
    onChange: (value: T) => void,
    config: { key: string },
): HTMLSelectElement {
    const element = h(
        'select',
        { class: 'tn-select', 'aria-label': label, 'data-key': config.key },
        ...choices.map(([choice, text]) => h('option', { value: choice }, text)),
    );

    element.value = value;
    element.addEventListener('change', () => onChange(element.value as T));

    return element;
}

export const statusChoices = STATUSES.map((status) => [status, STATUS_LABELS[status]] as const);
export const priorityChoices = PRIORITIES.map((priority) => [priority, PRIORITY_LABELS[priority]] as const);

/** For selects without a visible label, where "High" alone would not say what it describes. */
export const priorityLevelChoices: Choices<Priority> = priorityChoices.map(([value, label]) => [value, `${label} priority`] as const);
