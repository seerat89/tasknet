import { describe, expect, it } from 'vitest';
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
const saves = (host: ReturnType<typeof fakeHost>) => host.calls('update_task').map(([, args]) => args.status ?? args.priority);

describe('keyboard changes to status and priority', () => {
    it('does not save while arrow keys move through the options, then saves the final value on Enter', async () => {
        const { host } = await open(LIST);

        control(statusLabel).focus();
        press(statusLabel, 'ArrowDown', 'in_progress');
        press(statusLabel, 'ArrowDown', 'completed');
        await settle();

        expect(saves(host)).toEqual([]);
        expect(document.getElementById('tn-hint-status-1')!.hidden).toBe(false);
        expect(control(statusLabel).getAttribute('aria-describedby')).toBe('tn-hint-status-1');

        press(statusLabel, 'Enter');
        await settle();

        expect(saves(host)).toEqual(['completed']);
        expect(document.activeElement).toBe(control(statusLabel));
    });

    it.each(['Home', 'End', 'PageDown', 'c'])('also waits after %s', async (key) => {
        const { host } = await open(LIST);

        press(statusLabel, key, 'completed');
        await settle();

        expect(saves(host)).toEqual([]);
    });

    it('discards the choice when focus leaves, says so, and sends nothing', async () => {
        const { host } = await open(LIST);

        control(statusLabel).focus();
        press(statusLabel, 'ArrowDown', 'in_progress');
        press(statusLabel, 'Tab');
        control(`Priority of ${title}`).focus();
        await settle();

        expect(saves(host)).toEqual([]);
        expect(control<HTMLSelectElement>(statusLabel).value).toBe('todo');
        expect(document.getElementById('tn-hint-status-1')!.hidden).toBe(true);
        expect(spoken()).toBe('Change not saved.');
        expect(document.activeElement).toBe(control(`Priority of ${title}`));
    });

    it('shows no save hint when the keyboard returns to the saved value', async () => {
        const { host } = await open(LIST);

        press(statusLabel, 'ArrowDown', 'in_progress');
        press(statusLabel, 'ArrowUp', 'todo');
        press(statusLabel, 'Enter');
        await settle();

        expect(document.getElementById('tn-hint-status-1')!.hidden).toBe(true);
        expect(saves(host)).toEqual([]);
    });

    it('undoes the choice with Escape', async () => {
        const { host } = await open(LIST);

        control(statusLabel).focus();
        press(statusLabel, 'ArrowDown', 'in_progress');
        press(statusLabel, 'Escape');
        control<HTMLElement>(`Priority of ${title}`).focus();
        await settle();

        expect(saves(host)).toEqual([]);
        expect(control<HTMLSelectElement>(statusLabel).value).toBe('todo');
    });

    it('keeps an unsaved choice across a redraw', async () => {
        const { controller, host } = await open(LIST);

        control(statusLabel).focus();
        press(statusLabel, 'ArrowDown', 'in_progress');
        controller.setHostContext({ displayMode: 'fullscreen' });
        await settle();

        expect(saves(host)).toEqual([]);
        expect(control<HTMLSelectElement>(statusLabel).value).toBe('in_progress');
        expect(document.activeElement).toBe(control(statusLabel));

        press(statusLabel, 'Enter');
        await settle();
        expect(saves(host)).toEqual(['in_progress']);
    });

    it.each([
        ['Enter', {}],
        [' ', {}],
        ['ArrowDown', { altKey: true }],
    ])('saves at once when the value was picked from the opened list (%s)', async (key, modifiers) => {
        const { host } = await open(LIST);

        press(statusLabel, key, undefined, modifiers);
        enter(statusLabel, 'completed');
        await settle();

        expect(saves(host)).toEqual(['completed']);
    });

    it('saves a pointer choice at once, even after earlier key presses', async () => {
        const { host } = await open(DETAIL);

        press(statusLabel, 'ArrowDown');
        control(statusLabel).dispatchEvent(new Event('pointerdown'));
        control(statusLabel).dispatchEvent(new Event('pointerup'));
        enter(statusLabel, 'completed');
        await settle();

        expect(saves(host)).toEqual(['completed']);
    });

    it('keeps filtering immediate', async () => {
        await open(LIST);

        press('Filter by status', 'ArrowDown', 'completed');

        expect(text()).toContain('No tasks match these filters');
    });

    it('keeps focus on a control while its task is saving, and ignores further changes', async () => {
        const host = fakeHost();
        const pending = deferred<ToolResult>();

        host.handlers.update_task = () => pending.promise;
        await open(LIST, host);

        control(`Complete ${title}`).focus();
        control(`Complete ${title}`).click();
        enter(`Priority of ${title}`, 'high');

        expect(document.activeElement).toBe(control(`Complete ${title}`));
        expect(isDisabled(control(`Complete ${title}`))).toBe(true);
        expect(control<HTMLSelectElement>(`Priority of ${title}`).value).toBe('medium');
        expect(saves(host)).toEqual(['completed']);
    });
});

describe('moving on while a keyboard choice is unsaved', () => {
    const two = () => fakeHost({ tasks: [task(), task({ id: 2, title: 'Beta' })] });

    async function drafted(route: Route = LIST) {
        const mounted = await open(route, two());

        control(statusLabel).focus();
        press(statusLabel, 'ArrowDown', 'in_progress');

        return mounted;
    }

    /** A mouse click as a browser delivers it: focus moves on press, the click follows release. */
    async function mouseClick(find: () => HTMLElement) {
        const pressed = find();

        pressed.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        pressed.focus();
        await settle();
        // Nothing may be redrawn between press and release.
        expect(find()).toBe(pressed);
        pressed.dispatchEvent(new Event('pointerup', { bubbles: true }));
        pressed.click();
        await settle();
    }

    it.each([
        [`Complete ${title}`, [[1, 'completed']]],
        ['Complete Beta', [[2, 'completed']]],
    ])('sends only what "%s" asks for', async (label, expected) => {
        const { host } = await drafted();

        await mouseClick(() => control(label));

        expect(host.calls('update_task').map(([, args]) => [args.task_id, args.status])).toEqual(expected);
    });

    it('sends only the field picked in the other select', async () => {
        const { host } = await drafted();

        control(`Priority of ${title}`).dispatchEvent(new Event('pointerdown', { bubbles: true }));
        control(`Priority of ${title}`).focus();
        await settle();
        enter(`Priority of ${title}`, 'high');
        await settle();

        expect(host.calls('update_task').map(([, args]) => args)).toEqual([{ project_id: 1, task_id: 1, priority: 'high' }]);
    });

    it.each([
        [LIST, '← Launch plan'],
        [DETAIL, 'Edit task'],
    ])('leaves %o at once without saving', async (route, label) => {
        const { host } = await drafted(route);

        await mouseClick(() => buttons(label)[0]);

        expect(saves(host)).toEqual([]);
        expect(document.querySelector('h1')!.textContent).not.toBe('Tasks');
        expect(host.tasks[0].status).toBe('todo');
    });

    it('sends nothing when the opened list is used to pick the saved value again', async () => {
        const { host } = await drafted();

        control(statusLabel).dispatchEvent(new Event('pointerdown', { bubbles: true }));
        enter(statusLabel, 'todo');
        await settle();

        expect(saves(host)).toEqual([]);
        expect(document.getElementById('tn-hint-status-1')!.hidden).toBe(true);
    });

    it('shows a view the host asks for at once', async () => {
        const { controller, host } = await drafted();

        controller.handleToolResult({ structuredContent: { view: 'projects' } });
        await settle();

        expect(document.querySelector('h1')!.textContent).toBe('Projects');
        expect(saves(host)).toEqual([]);
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
