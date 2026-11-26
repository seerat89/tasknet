import { vi } from 'vitest';
import { createController } from '../../resources/mcp-app/controller';
import type { HostBridge, Project, Task, ToolResult } from '../../resources/mcp-app/types';

/** Test data only. The widget itself never contains sample data. */
export function project(overrides: Partial<Project> = {}): Project {
    return { id: 1, name: 'Launch plan', description: 'Ship it', tasks_count: 0, ...overrides };
}

export function task(overrides: Partial<Task> = {}): Task {
    return {
        id: 1,
        project_id: 1,
        title: 'Write the announcement',
        description: 'Draft and review',
        status: 'todo',
        priority: 'medium',
        due_date: null,
        ...overrides,
    };
}

export const WRITE_TOOLS = ['create_project', 'update_project', 'create_task', 'update_task'];

const ok = (structuredContent: unknown): ToolResult => ({ content: [], structuredContent, isError: false });

/** A rejection TaskNet marks as having changed nothing. */
export function refusal(message: string, fieldErrors?: Record<string, string[]>): ToolResult {
    return {
        content: [{ type: 'text', text: message }],
        isError: true,
        _meta: fieldErrors ? { 'tasknet/validationErrors': fieldErrors } : { 'tasknet/refused': true },
    };
}

/** An unexpected server error, which may have happened after saving. */
export function serverError(): ToolResult {
    return { content: [{ type: 'text', text: 'An internal server error occurred.' }], isError: true };
}

type Handler = (args: Record<string, unknown>) => ToolResult | Promise<ToolResult>;

/** An in-memory stand-in for TaskNet behind the host bridge. */
export function fakeHost(seed: { projects?: Project[]; tasks?: Task[]; fullscreen?: boolean } = {}) {
    const projects = seed.projects ?? [project()];
    const tasks = seed.tasks ?? [task()];
    const counted = (item: Project): Project => ({ ...item, tasks_count: tasks.filter((entry) => entry.project_id === item.id).length });
    const findProject = (id: unknown) => projects.find((item) => item.id === id);
    const findTask = (args: Record<string, unknown>) =>
        tasks.find((item) => item.id === args.task_id && item.project_id === args.project_id);

    const handlers: Record<string, Handler> = {
        list_projects: () => ok({ projects: projects.map(counted) }),
        get_project: ({ project_id }) => {
            const found = findProject(project_id);

            return found ? ok({ project: counted(found) }) : refusal('Project not found.');
        },
        list_tasks: ({ project_id }) =>
            findProject(project_id)
                ? ok({ project_id, tasks: tasks.filter((item) => item.project_id === project_id).map((item) => ({ ...item })) })
                : refusal('Project not found.'),
        get_task: (args) => {
            const found = findTask(args);

            return found ? ok({ task: { ...found } }) : refusal('Task not found.');
        },
        create_project: (args) => {
            const created = project({ id: projects.length + 100, name: String(args.name), description: String(args.description ?? '') || null });

            projects.push(created);

            return ok({ project: created });
        },
        update_project: ({ project_id, ...changes }) => {
            const found = findProject(project_id)!;

            Object.assign(found, changes);

            return ok({ project: counted(found) });
        },
        create_task: (args) => {
            const created = task({ ...(args as Partial<Task>), id: tasks.length + 100 });

            tasks.push(created);

            return ok({ task: created });
        },
        update_task: (args) => {
            const found = findTask(args)!;
            const { project_id: _project, task_id: _task, ...changes } = args;

            Object.assign(found, changes);

            return ok({ task: { ...found } });
        },
    };

    const bridge = {
        callTool: vi.fn(async (name: string, args: Record<string, unknown>) => handlers[name](args)),
        updateContext: vi.fn<(text: string) => void>(),
        canFullscreen: vi.fn(() => seed.fullscreen ?? false),
        requestFullscreen: vi.fn(async () => true),
    } satisfies HostBridge;

    return {
        bridge,
        handlers,
        projects,
        tasks,
        calls: (name?: string) => bridge.callTool.mock.calls.filter(([tool]) => name === undefined || tool === name),
        writes: () => bridge.callTool.mock.calls.filter(([tool]) => WRITE_TOOLS.includes(tool)),
        /** The latest context, which is the only one a host keeps. */
        context: () => bridge.updateContext.mock.lastCall?.[0] ?? '',
    };
}

export function mount(host = fakeHost()) {
    document.body.replaceChildren();

    const root = document.createElement('main');

    document.body.append(root);

    return { host, root, controller: createController(root, host.bridge) };
}

/** Let pending promises and the redraws that follow them finish. */
export async function settle(): Promise<void> {
    for (let turn = 0; turn < 5; turn++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
}

export function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<T>((onResolve, onReject) => {
        resolve = onResolve;
        reject = onReject;
    });

    return { promise, resolve, reject };
}

export function buttons(label: string | RegExp): HTMLButtonElement[] {
    return [...document.querySelectorAll('button')].filter((element) =>
        typeof label === 'string' ? element.textContent === label : label.test(element.textContent ?? ''),
    );
}

export function click(label: string | RegExp): void {
    const [found] = buttons(label);

    if (!found) {
        throw new Error(`No button ${label} in: ${document.body.textContent}`);
    }

    found.click();
}

export function control<T extends HTMLElement = HTMLInputElement>(label: string): T {
    const named = document.querySelector<T>(`[aria-label="${label}"]`);

    if (named) {
        return named;
    }

    const heading = [...document.querySelectorAll('label')].find((element) => element.textContent === label);
    const found = heading && document.getElementById(heading.htmlFor);

    if (!found) {
        throw new Error(`No control labelled ${label} in: ${document.body.textContent}`);
    }

    return found as T;
}

/** Choose or type a value, as a pointer user would: no key presses. */
export function enter(label: string, value: string): void {
    const element = control(label);

    element.value = value;
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
}

/** Press a key on a control, applying the value a browser would move to. */
export function press(label: string, key: string, value?: string, modifiers: KeyboardEventInit = {}): KeyboardEvent {
    const element = control(label);
    const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...modifiers });

    element.dispatchEvent(event);

    if (value !== undefined) {
        element.value = value;
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    return event;
}

/** Busy controls are marked with aria-disabled so they keep focus; form fields use disabled. */
export function isDisabled(element: Element): boolean {
    return (element as HTMLButtonElement).disabled === true || element.getAttribute('aria-disabled') === 'true';
}

export const text = (): string => document.querySelector('.tn-root')?.textContent ?? '';

/** The visible error blocks. */
export const errors = (): string => [...document.querySelectorAll('.tn-alert')].map((element) => element.textContent).join(' ');

/** The visible success and information blocks. */
export const notices = (): string => [...document.querySelectorAll('.tn-notice')].map((element) => element.textContent).join(' ');

/** What the persistent live region currently says to screen readers. */
export const spoken = (role: 'status' | 'alert' = 'status'): string => document.querySelector(`main > [role="${role}"]`)?.textContent ?? '';
