type Child = Node | string | number | false | null | undefined;
type Props = Record<string, string | number | boolean | ((event: never) => void) | undefined>;

/**
 * Create an element. Text is only ever added as text nodes and there is no HTML string input,
 * so workspace data cannot become markup.
 */
export function h<K extends keyof HTMLElementTagNameMap>(tag: K, props: Props = {}, ...children: Child[]): HTMLElementTagNameMap[K] {
    const element = document.createElement(tag);

    for (const [name, value] of Object.entries(props)) {
        if (value === undefined || value === false) {
            continue;
        }

        if (typeof value === 'function') {
            element.addEventListener(name.slice(2).toLowerCase(), value as EventListener);
        } else {
            element.setAttribute(name, value === true ? '' : String(value));
        }
    }

    for (const child of children) {
        if (child !== false && child !== null && child !== undefined) {
            element.append(typeof child === 'number' ? String(child) : child);
        }
    }

    return element;
}

let replacing = false;

/** True while swap() is removing old content. */
export const isReplacing = (): boolean => replacing;

/**
 * Replace a container's content. Chromium fires `change` on a select whose list is open when it is
 * removed, so selects ignore changes made while this runs.
 */
export function swap(container: Element, ...nodes: Node[]): void {
    replacing = true;

    try {
        container.replaceChildren(...nodes);
    } finally {
        replacing = false;
    }
}

export interface Painter {
    (...nodes: Array<Node | false | null | undefined>): void;
    /** Focus the control with this data-key after the next redraw, when the focused one is about to go. */
    focusNext(key: string): void;
}

/**
 * Replace a container's content, keeping keyboard focus on the control with the same data-key.
 *
 * If that control no longer exists, focus moves to the view's heading instead of being lost.
 */
export function createPainter(container: HTMLElement): Painter {
    let focusKey: string | undefined;
    let requested: string | undefined;

    const paint = (...nodes: Array<Node | false | null | undefined>): void => {
        const active = document.activeElement;

        if (active instanceof HTMLElement && container.contains(active)) {
            focusKey = active.dataset.key;
        } else if (active !== null && active !== document.body) {
            focusKey = undefined;
        }

        if (requested !== undefined) {
            focusKey = requested;
            requested = undefined;
        }

        swap(container, ...nodes.filter((node): node is Node => node instanceof Node));

        if (focusKey === undefined) {
            return;
        }

        const target =
            [...container.querySelectorAll<HTMLElement>('[data-key]')].find((element) => element.dataset.key === focusKey) ??
            container.querySelector<HTMLElement>('h1');

        // While loading there is neither: a later redraw restores focus.
        if (target) {
            target.focus();
            focusKey = undefined;
        }
    };

    return Object.assign(paint, {
        focusNext(key: string): void {
            requested = key;
        },
    });
}
