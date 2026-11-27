import { describe, expect, it, vi } from 'vitest';
import type { Route, ToolResult } from '../../resources/mcp-app/types';
import {
    buttons,
    click,
    control,
    deferred,
    enter,
    errors,
    fakeHost,
    isDisabled,
    mount,
    notices,
    press,
    project,
    refusal,
    serverError,
    settle,
    spoken,
    task,
    text,
} from './support';

async function open(route: Route, host = fakeHost()) {
    const mounted = mount(host);

    mounted.controller.open(route);
    await settle();

    return mounted;
}

const LIST: Route = { view: 'tasks', project_id: 1 };
const DETAIL: Route = { view: 'task', project_id: 1, task_id: 1 };
const title = 'Write the announcement';
const statusLabel = `Status of ${title}`;
// While saving, the same button reads "Saving…" and its name says so.
const saveButton = (name = title) =>
    document.querySelector<HTMLButtonElement>(`[aria-label="Save changes to ${name}"], [aria-label="Saving changes to ${name}"]`)!;
const discardButton = (name = title) => control<HTMLButtonElement>(`Discard changes to ${name}`);
const list = (label = statusLabel) => control<HTMLSelectElement>(label);
const sent = (host: ReturnType<typeof fakeHost>) => host.calls('update_task').map(([, args]) => args);
const priorityLabel = `Priority of ${title}`;

describe.each([
    ['task list', LIST],
    ['task view', DETAIL],
])('choosing a status or priority in the %s', (_name, route) => {
    it.each([
        ['a pointer or touch pick', () => enter(statusLabel, 'completed')],
        [
            'arrow keys on the closed list',
            () => {
                press(statusLabel, 'ArrowDown', 'in_progress');
                press(statusLabel, 'ArrowDown', 'completed');
            },
        ],
        [
            'a pick from the list opened with the keyboard',
            () => {
                press(statusLabel, ' ');
                enter(statusLabel, 'completed');
            },
        ],
    ])('writes nothing after %s, and offers Save and Discard', async (_way, choose) => {
        const { host } = await open(route);

        expect(saveButton().hidden).toBe(true);
        expect(discardButton().hidden).toBe(true);
        expect(list().hasAttribute('aria-describedby')).toBe(false);

        choose();
        await settle();

        expect(host.writes()).toEqual([]);
        expect(host.tasks[0].status).toBe('todo');
        expect(list().value).toBe('completed');
        expect(saveButton().hidden).toBe(false);
        expect(discardButton().hidden).toBe(false);
        expect(document.getElementById(list().getAttribute('aria-describedby')!)!.textContent).toBe(
            'Not saved yet. Press Enter or choose Save.',
        );
        expect(list(priorityLabel).hasAttribute('aria-describedby')).toBe(false);
    });

    it('announces an unsaved choice once, not at every step', async () => {
        await open(route);

        enter(statusLabel, 'in_progress');
        await settle();
        expect(spoken()).toBe('Not saved yet. Press Enter or choose Save.');

        const region = document.querySelector('main > [role="status"]')!;
        let changes = 0;
        const observer = new MutationObserver((records) => (changes += records.length));

        observer.observe(region, { childList: true, characterData: true, subtree: true });
        enter(statusLabel, 'completed');
        enter(priorityLabel, 'high');
        await settle();
        observer.disconnect();

        expect(changes).toBe(0);
    });

    it('saves both chosen fields with one Save, and moves focus to a list that stays', async () => {
        const { host } = await open(route);

        enter(priorityLabel, 'high');
        enter(statusLabel, 'in_progress');
        saveButton().focus();
        saveButton().click();
        await settle();

        expect(sent(host)).toEqual([{ project_id: 1, task_id: 1, priority: 'high', status: 'in_progress' }]);
        expect(saveButton().hidden).toBe(true);
        expect(discardButton().hidden).toBe(true);
        expect(list().value).toBe('in_progress');
        expect(document.activeElement).toBe(list());
        expect(spoken()).toBe('Task saved.');
    });

    it('sends only the field that changed', async () => {
        const { host } = await open(route);

        enter(priorityLabel, 'low');
        saveButton().focus();
        saveButton().click();
        await settle();

        expect(sent(host)).toEqual([{ project_id: 1, task_id: 1, priority: 'low' }]);
        expect(document.activeElement).toBe(list(priorityLabel));
    });

    it('saves once on Enter and keeps focus on the list', async () => {
        const { host } = await open(route);

        list().focus();
        press(statusLabel, 'ArrowDown', 'in_progress');
        const event = press(statusLabel, 'Enter');
        await settle();

        expect(event.defaultPrevented).toBe(true);
        expect(sent(host)).toEqual([{ project_id: 1, task_id: 1, status: 'in_progress' }]);
        expect(document.activeElement).toBe(list());
    });

    it('leaves Enter alone on a list with nothing to save, so it can open', async () => {
        const { host } = await open(route);

        expect(press(statusLabel, 'Enter').defaultPrevented).toBe(false);
        enter(statusLabel, 'completed');
        expect(press(priorityLabel, 'Enter').defaultPrevented).toBe(false);
        await settle();

        expect(host.writes()).toEqual([]);
    });

    it.each([
        [
            'Escape',
            () => {
                list().focus();
                press(statusLabel, 'Escape');
            },
        ],
        [
            'the Discard button',
            () => {
                discardButton().focus();
                discardButton().click();
            },
        ],
    ])('discards with %s without writing anything', async (_way, discard) => {
        const { host } = await open(route);

        enter(statusLabel, 'completed');
        discard();
        await settle();

        expect(host.writes()).toEqual([]);
        expect(list().value).toBe('todo');
        expect(saveButton().hidden).toBe(true);
        expect(list().hasAttribute('aria-describedby')).toBe(false);
        expect(document.activeElement).toBe(list());
        expect(spoken()).toBe('Change discarded.');
    });

    it('has nothing to save once the saved value is chosen again', async () => {
        const { host } = await open(route);

        enter(statusLabel, 'completed');
        enter(statusLabel, 'todo');
        press(statusLabel, 'Enter');
        await settle();

        expect(saveButton().hidden).toBe(true);
        expect(host.writes()).toEqual([]);
    });

    it('keeps an unsaved choice, without writing, when focus moves on and the view redraws', async () => {
        const { controller, host } = await open(route);

        list().focus();
        press(statusLabel, 'ArrowDown', 'in_progress');
        list(priorityLabel).focus();
        controller.setHostContext({ displayMode: 'fullscreen' });
        await settle();

        expect(host.writes()).toEqual([]);
        expect(list().value).toBe('in_progress');
        expect(saveButton().hidden).toBe(false);
        expect(document.activeElement).toBe(list(priorityLabel));
    });

    it('sends one request however Save and Enter are repeated, and holds the choice while saving', async () => {
        const host = fakeHost();
        const pending = deferred<ToolResult>();

        host.handlers.update_task = () => pending.promise;
        await open(route, host);
        enter(statusLabel, 'completed');
        list().focus();
        saveButton().click();
        saveButton().click();
        press(statusLabel, 'Enter');
        press(statusLabel, 'Enter');
        press(statusLabel, 'Escape');
        discardButton().click();
        control<HTMLButtonElement>(`Complete ${title}`).click();
        enter(priorityLabel, 'high');

        expect(sent(host)).toHaveLength(1);
        expect(saveButton().textContent).toBe('Saving…');
        expect(isDisabled(saveButton())).toBe(true);
        expect(isDisabled(discardButton())).toBe(true);
        expect(list().value).toBe('completed');
        expect(list(priorityLabel).value).toBe('medium');

        pending.resolve({ structuredContent: { task: task({ status: 'completed' }) } });
        await settle();
        expect(saveButton().hidden).toBe(true);
    });

    it('forgets the choice and shows the real value when a save is refused', async () => {
        const host = fakeHost();

        host.handlers.update_task = () => refusal('The selected status is invalid.', { status: ['The selected status is invalid.'] });
        await open(route, host);
        enter(statusLabel, 'completed');
        saveButton().click();
        await settle();

        expect(errors()).toContain('The selected status is invalid.');
        expect(list().value).toBe('todo');
        expect(saveButton().hidden).toBe(true);
    });

    it('treats Complete as its own action, dropping an unsaved choice without sending it', async () => {
        const { host } = await open(route);

        enter(priorityLabel, 'high');
        control<HTMLButtonElement>(`Complete ${title}`).click();
        await settle();

        expect(sent(host)).toEqual([{ project_id: 1, task_id: 1, status: 'completed' }]);
        expect(list(priorityLabel).value).toBe('medium');
        expect(saveButton().hidden).toBe(true);
    });

    it('discards an unsaved choice when the user leaves the view', async () => {
        const { controller, host } = await open(route);

        enter(statusLabel, 'completed');
        controller.open({ view: 'projects' });
        await settle();
        controller.open(route);
        await settle();

        expect(host.writes()).toEqual([]);
        expect(list().value).toBe('todo');
        expect(saveButton().hidden).toBe(true);
    });

    // Chromium fires change on a select whose list is open while it is being removed.
    it('ignores a change raised while a redraw removes the list', async () => {
        const { controller, host } = await open(route);
        const original = Element.prototype.replaceChildren;
        const removed = list();
        const spy = vi.spyOn(Element.prototype, 'replaceChildren').mockImplementation(function (this: Element, ...nodes) {
            if (this.contains(removed)) {
                removed.value = 'completed';
                removed.dispatchEvent(new Event('change', { bubbles: true }));
            }

            original.apply(this, nodes);
        });

        controller.setHostContext({ displayMode: 'fullscreen' });
        spy.mockRestore();
        await settle();

        expect(host.writes()).toEqual([]);
        expect(list()).not.toBe(removed);
        expect(list().value).toBe('todo');
        expect(saveButton().hidden).toBe(true);

        // A redraw afterwards must not reveal a choice nobody made.
        controller.setHostContext({ displayMode: 'inline' });
        expect(saveButton().hidden).toBe(true);
    });
});

describe('unsaved choices in a list of tasks', () => {
    const two = () => fakeHost({ tasks: [task(), task({ id: 2, title: 'Beta' })] });

    it('keeps another task’s unsaved choice when one is saved', async () => {
        const { host } = await open(LIST, two());

        enter(statusLabel, 'in_progress');
        enter('Priority of Beta', 'high');
        saveButton('Beta').click();
        await settle();

        expect(sent(host)).toEqual([{ project_id: 1, task_id: 2, priority: 'high' }]);
        expect(list().value).toBe('in_progress');
        expect(saveButton().hidden).toBe(false);
        expect(text()).not.toContain('An unsaved choice was cleared');
    });

    it.each([
        ['to something else', 'completed'],
        ['to the very value chosen here', 'in_progress'],
    ])('drops a choice whose task has since changed %s, and says so', async (_how, status) => {
        const { host } = await open(LIST, two());

        enter(statusLabel, 'in_progress');
        // The assistant, or the web app, changes the same task.
        host.tasks[0].status = status as 'completed';
        control<HTMLButtonElement>('Complete Beta').click();
        await settle();

        expect(sent(host)).toEqual([{ project_id: 1, task_id: 2, status: 'completed' }]);
        expect(list().value).toBe(status);
        expect(saveButton().hidden).toBe(true);
        expect(notices()).toContain('An unsaved choice was cleared because its task changed.');
        expect(spoken()).toBe('Task saved. An unsaved choice was cleared because its task changed.');
    });

    it('keeps a choice when only another field of its task changed elsewhere', async () => {
        const { host } = await open(LIST, two());

        enter(statusLabel, 'in_progress');
        host.tasks[0].priority = 'high';
        control<HTMLButtonElement>('Complete Beta').click();
        await settle();

        expect(list().value).toBe('in_progress');
        saveButton().click();
        await settle();
        expect(sent(host)[1]).toEqual({ project_id: 1, task_id: 1, status: 'in_progress' });
    });

    it('does not call a choice stale while its own save is still in flight', async () => {
        const host = two();
        const save = host.handlers.update_task;
        const pending = deferred<ToolResult>();

        host.handlers.update_task = async (args) => {
            const result = await save(args);

            return args.task_id === 1 ? pending.promise : result;
        };
        await open(LIST, host);
        enter(statusLabel, 'in_progress');
        saveButton().click();
        control<HTMLButtonElement>('Complete Beta').click();
        await settle();

        expect(text()).not.toContain('An unsaved choice was cleared');
        expect(saveButton().textContent).toBe('Saving…');

        pending.resolve({ structuredContent: { task: task({ status: 'in_progress' }) } });
        await settle();
        expect(saveButton().hidden).toBe(true);
    });

    it('shows a choice again, still unsaved, after its task was filtered out of sight', async () => {
        const { host } = await open(LIST);

        enter(statusLabel, 'completed');
        enter('Filter by status', 'in_progress');
        enter('Filter by status', '');

        expect(list().value).toBe('completed');
        expect(saveButton().hidden).toBe(false);
        expect(host.writes()).toEqual([]);
    });

    it('keeps filtering immediate', async () => {
        await open(LIST);

        enter('Filter by status', 'completed');

        expect(text()).toContain('No tasks match these filters');
    });
});

describe('announcements and focus', () => {
    it('uses two live regions that survive every redraw', async () => {
        const { controller } = await open(LIST);
        const regions = () => [...document.querySelectorAll('[role="status"], [role="alert"]')];
        const first = regions();

        expect(first).toHaveLength(2);

        controller.setHostContext({ displayMode: 'fullscreen' });
        enter('Filter by priority', 'medium');

        expect(regions()).toEqual(first);
    });

    it('announces saving and the result', async () => {
        const host = fakeHost();
        const pending = deferred<ToolResult>();

        host.handlers.update_task = () => pending.promise;
        await open(LIST, host);
        control(`Complete ${title}`).click();
        await settle();
        expect(spoken()).toBe('Saving…');

        pending.resolve({ structuredContent: { task: task({ status: 'completed' }) } });
        await settle();
        expect(spoken()).toBe('Task saved.');
    });

    it('announces a failure once, and not again when the view is redrawn', async () => {
        const host = fakeHost();

        host.handlers.update_task = () => refusal('Task not found.');

        const { controller } = await open(LIST, host);

        control(`Complete ${title}`).click();
        await settle();
        expect(spoken('alert')).toBe(`${title}: Task not found.`);

        const region = document.querySelector('main > [role="alert"]')!;
        let changes = 0;
        const observer = new MutationObserver((records) => (changes += records.length));

        observer.observe(region, { childList: true, characterData: true, subtree: true });
        controller.setHostContext({ displayMode: 'fullscreen' });
        await settle();
        observer.disconnect();

        expect(changes).toBe(0);
    });

    it('announces the message that arrives with a new view', async () => {
        await open({ view: 'task_form', project_id: 1 });

        enter('Title', 'Design');
        click('Create task');
        await settle();

        expect(spoken()).toBe('Task created.');
    });

    it('moves focus to a form error that names no field', async () => {
        const host = fakeHost();

        host.handlers.create_project = () => refusal('This action is unauthorized.');
        await open({ view: 'project_form' }, host);
        enter('Name', 'Website');
        click('Create project');
        await settle();

        expect(document.activeElement?.textContent).toBe('This action is unauthorized.');
        expect(spoken('alert')).toBe('This action is unauthorized.');
    });

    it('moves focus to the first newly shown item after "Show all"', async () => {
        const projects = Array.from({ length: 7 }, (_, index) => project({ id: index + 1, name: `Project ${index + 1}` }));

        await open({ view: 'projects' }, fakeHost({ projects }));
        buttons('Show all 7 projects')[0].focus();
        click('Show all 7 projects');

        expect(document.activeElement?.textContent).toBe('Project 6');
    });

    it('moves focus to the filter after clearing filters', async () => {
        await open(LIST);
        enter('Filter by status', 'completed');
        buttons('Clear filters')[0].focus();
        click('Clear filters');

        expect(document.activeElement).toBe(control('Filter by status'));
    });

    it('moves focus to the heading when the focused task leaves the filtered list', async () => {
        await open(LIST);
        enter('Filter by status', 'todo');
        control(`Complete ${title}`).focus();
        control(`Complete ${title}`).click();
        await settle();

        expect(document.activeElement?.tagName).toBe('H1');
    });

    it('keeps focus on the heading across a redraw', async () => {
        const { controller } = await open(LIST);

        enter('Filter by status', 'todo');
        control(`Complete ${title}`).focus();
        control(`Complete ${title}`).click();
        await settle();
        controller.setHostContext({ locale: 'fi-FI' });

        expect(document.activeElement?.tagName).toBe('H1');
    });

    it('keeps focus after a retry succeeds', async () => {
        const host = fakeHost();
        const original = host.handlers.list_projects;

        host.handlers.list_projects = () => Promise.reject(new Error('offline'));
        await open({ view: 'projects' }, host);
        host.handlers.list_projects = original;
        buttons('Try again')[0].focus();
        click('Try again');
        await settle();

        expect(document.activeElement?.tagName).toBe('H1');
    });
});

describe('messages and context stay current', () => {
    it('says why a change is unknown when the current values cannot be loaded either', async () => {
        const host = fakeHost();

        await open(LIST, host);
        host.handlers.update_task = () => Promise.reject(new Error('429'));
        host.handlers.list_tasks = () => Promise.reject(new Error('429'));
        control(`Complete ${title}`).click();
        await settle();

        expect(errors()).toContain('Wait a moment and try again.');
        expect(errors()).toContain('may be out of date');
        expect(host.context()).toContain('may or may not have been saved');
    });

    it('describes the view with the new values after a change', async () => {
        const { host } = await open(LIST);

        control(`Complete ${title}`).click();
        await settle();

        expect(host.context()).toContain(`#1 "${title}" (Completed)`);
        expect(host.context()).not.toContain(`#1 "${title}" (Todo)`);
    });

    it('clears the arrival message when the next change starts', async () => {
        const host = fakeHost();

        await open({ view: 'task_form', project_id: 1 }, host);
        enter('Title', 'Design');
        click('Create task');
        await settle();
        expect(notices()).toContain('Task created.');

        host.handlers.update_task = () => refusal('Task not found.');
        control('Complete Design').click();
        await settle();

        expect(notices()).not.toContain('Task created.');
        expect(errors()).toContain('Task not found.');
    });

    it('removes an earlier success message when a later change fails', async () => {
        const host = fakeHost();

        await open(LIST, host);
        control(`Complete ${title}`).click();
        await settle();
        host.handlers.update_task = () => refusal('Task not found.');
        control(`Reopen ${title}`).click();
        await settle();

        expect(notices()).not.toContain('Task saved.');
    });

    it('drops a deleted task from the list and explains why', async () => {
        const host = fakeHost({ tasks: [task(), task({ id: 2, title: 'Beta' })] });

        await open(LIST, host);
        host.tasks.splice(0, 1);
        host.handlers.update_task = () => refusal('Task not found.');
        control(`Complete ${title}`).click();
        await settle();

        expect(document.querySelectorAll('.tn-item')).toHaveLength(1);
        expect(errors()).toContain(`${title}: Task not found.`);
    });

    it('replaces a deleted task with an explanation and a way back', async () => {
        const host = fakeHost();

        await open(DETAIL, host);
        host.tasks.splice(0, 1);
        host.handlers.update_task = () => refusal('Task not found.');
        control(`Complete ${title}`).click();
        await settle();

        expect(text()).toContain('This task is no longer available.');
        expect(document.querySelector('select')).toBeNull();
        expect(host.context()).toContain('task #1 is no longer available');
        expect(buttons('← Tasks')).toHaveLength(1);
    });

    it('keeps the task and its field error when a change fails validation', async () => {
        const host = fakeHost();

        host.handlers.update_task = () => refusal('The selected status is invalid.', { status: ['The selected status is invalid.'] });
        await open(DETAIL, host);
        control(`Complete ${title}`).click();
        await settle();

        expect(host.calls('get_task')).toHaveLength(1);
        expect(errors()).toContain('The selected status is invalid.');
    });

    it('shows the saved state, not an error, when an unconfirmed change did happen', async () => {
        const host = fakeHost();
        const save = host.handlers.update_task;

        host.handlers.update_task = async (args) => {
            await save(args);

            return serverError();
        };
        await open(DETAIL, host);
        control(`Complete ${title}`).click();
        await settle();

        expect(errors()).toBe('');
        expect(notices()).toContain('The change could not be confirmed. The current values are shown.');
        expect(control<HTMLSelectElement>(statusLabel).value).toBe('completed');
    });

    it('tells the model when a view could not be loaded', async () => {
        const { controller, host } = await open(LIST);

        host.handlers.get_task = () => Promise.reject(new Error('offline'));
        controller.open(DETAIL);
        await settle();

        expect(host.context()).toContain('could not be loaded');
        expect(host.context()).not.toContain('tasks of project');
    });

    it('does not claim current values when only a superseded reload succeeded', async () => {
        const host = fakeHost({ tasks: [task(), task({ id: 2, title: 'Beta' })] });

        await open(LIST, host);

        const first = deferred<ToolResult>();
        const second = deferred<ToolResult>();
        const reloads = [first, second];

        host.handlers.update_task = serverError;
        host.handlers.list_tasks = () => reloads.shift()!.promise;
        control(`Complete ${title}`).click();
        await settle();
        control('Complete Beta').click();
        await settle();
        second.reject(new Error('offline'));
        await settle();
        first.resolve({ structuredContent: { tasks: host.tasks } });
        await settle();

        expect(notices()).not.toContain('The current values are shown.');
        expect(errors()).toContain('may be out of date');
        expect(isDisabled(control(`Complete ${title}`))).toBe(false);
        expect(isDisabled(control('Complete Beta'))).toBe(false);
    });

    it('can refresh a single task', async () => {
        const { host } = await open(DETAIL);

        click('Refresh');
        await settle();

        expect(host.calls('get_task')).toHaveLength(2);
    });
});
