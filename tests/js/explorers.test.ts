import { describe, expect, it } from 'vitest';
import { buttons, click, control, deferred, enter, errors, fakeHost, isDisabled, mount, notices, project, refusal, serverError, settle, task, text } from './support';
import type { ToolResult } from '../../resources/mcp-app/types';

describe('starting point', () => {
    it('shows a loading state until the tool result arrives, then the requested view', async () => {
        const { controller, host } = mount();

        expect(text()).toContain('Opening TaskNet…');
        expect(host.calls()).toHaveLength(0);

        controller.handleToolResult({ structuredContent: { view: 'projects' } });

        expect(text()).toContain('Loading…');
        await settle();
        expect(text()).toContain('Launch plan');
    });

    it.each([
        [{ view: 'projects' }, 'Projects'],
        [{ view: 'project', project_id: 1 }, 'Completed'],
        [{ view: 'tasks', project_id: 1 }, 'All statuses'],
        [{ view: 'task', project_id: 1, task_id: 1 }, 'Edit task'],
        [{ view: 'project_form' }, 'Create project'],
        [{ view: 'project_form', project_id: 1 }, 'Save changes'],
        [{ view: 'task_form' }, 'Create task'],
        [{ view: 'task_form', project_id: 1, task_id: 1 }, 'Save changes'],
    ])('opens %o without writing anything', async (route, expected) => {
        const { controller, host } = mount();

        controller.handleToolResult({ structuredContent: route });
        await settle();

        expect(text()).toContain(expected);
        expect(host.writes()).toEqual([]);
    });

    it.each([[{ view: 'admin' }], [{ view: 'tasks' }], [{ view: 'task', project_id: '1', task_id: 1 }], [undefined]])(
        'rejects the unusable starting point %o',
        (structuredContent) => {
            const { controller, host } = mount();

            controller.handleToolResult({ structuredContent });

            expect(text()).toContain('TaskNet could not open this view.');
            expect(host.calls()).toHaveLength(0);
        },
    );

    it('shows why a view tool failed and offers a way on', async () => {
        const { controller } = mount();

        controller.handleToolResult(refusal('Project not found.'));
        expect(errors()).toContain('Project not found.');

        click('Browse projects');
        await settle();
        expect(text()).toContain('Launch plan');
    });

    it('reports a cancelled call', () => {
        const { controller } = mount();

        controller.handleCancelled();

        expect(text()).toContain('cancelled');
    });
});

describe('project explorer', () => {
    it('lists names, descriptions, task counts and the total', async () => {
        const { controller } = mount(
            fakeHost({ projects: [project(), project({ id: 2, name: 'Website', description: null })], tasks: [task()] }),
        );

        controller.open({ view: 'projects' });
        await settle();

        expect(text()).toContain('2 projects');
        expect(text()).toContain('Ship it');
        expect(text()).toContain('1 task');
        expect(text()).toContain('0 tasks');
        expect(text()).toContain('No description');
    });

    it('has an empty state that leads to the create form', async () => {
        const { controller, host } = mount(fakeHost({ projects: [], tasks: [] }));

        controller.open({ view: 'projects' });
        await settle();
        expect(text()).toContain('No projects yet');

        click('Create a project');
        expect(text()).toContain('New project');
        expect(host.writes()).toEqual([]);
    });

    it('derives progress and statistics from the project tasks', async () => {
        const { controller } = mount(
            fakeHost({
                tasks: [
                    task({ id: 1, status: 'completed', due_date: '2000-01-01' }),
                    task({ id: 2, status: 'in_progress', due_date: '2000-01-01' }),
                    task({ id: 3, status: 'todo', due_date: '2999-01-01' }),
                    task({ id: 4, status: 'todo' }),
                ],
            }),
        );

        controller.open({ view: 'projects' });
        await settle();
        click('Launch plan');
        await settle();

        const figures = Object.fromEntries(
            [...document.querySelectorAll('.tn-stat')].map((stat) => [
                stat.querySelector('dt')!.textContent,
                stat.querySelector('dd')!.textContent,
            ]),
        );

        expect(figures).toEqual({ Total: '4', Completed: '1', 'In progress': '1', Pending: '2', Overdue: '1' });
        expect(text()).toContain('1 of 4 completed (25%)');

        click('View tasks');
        await settle();
        expect(text()).toContain('All statuses');
    });

    it('has an empty state for a project without tasks', async () => {
        const { controller } = mount(fakeHost({ tasks: [] }));

        controller.open({ view: 'project', project_id: 1 });
        await settle();

        expect(text()).toContain('No tasks yet');
        expect(buttons('Add a task')).toHaveLength(1);
    });

    it('shows a compact list inline and everything after the user asks for it', async () => {
        const projects = Array.from({ length: 8 }, (_, index) => project({ id: index + 1, name: `Project ${index + 1}` }));
        const { controller, host } = mount(fakeHost({ projects, fullscreen: true }));

        controller.setHostContext({ displayMode: 'inline' });
        controller.open({ view: 'projects' });
        await settle();

        expect(document.querySelectorAll('.tn-item')).toHaveLength(5);
        expect(host.bridge.requestFullscreen).not.toHaveBeenCalled();

        click('Show all 8 projects');

        expect(document.querySelectorAll('.tn-item')).toHaveLength(8);
        expect(host.bridge.requestFullscreen).toHaveBeenCalledOnce();

        controller.setHostContext({ displayMode: 'fullscreen' });
        controller.setHostContext({ displayMode: 'inline' });

        expect(document.querySelectorAll('.tn-item')).toHaveLength(5);
    });

    it('expands in place when the host has no fullscreen mode', async () => {
        const projects = Array.from({ length: 6 }, (_, index) => project({ id: index + 1, name: `Project ${index + 1}` }));
        const { controller, host } = mount(fakeHost({ projects }));

        controller.open({ view: 'projects' });
        await settle();
        click('Show all 6 projects');

        expect(document.querySelectorAll('.tn-item')).toHaveLength(6);
        expect(host.bridge.requestFullscreen).not.toHaveBeenCalled();
    });
});

describe('task explorer', () => {
    const tasks = () => [
        task({ id: 1, title: 'Alpha', status: 'todo', priority: 'high', due_date: '2000-01-01' }),
        task({ id: 2, title: 'Beta', status: 'completed', priority: 'low' }),
        task({ id: 3, title: 'Gamma', status: 'in_progress', priority: 'high' }),
    ];
    const titles = () => [...document.querySelectorAll('.tn-item .tn-link')].map((element) => element.textContent);

    async function open(host = fakeHost({ tasks: tasks() })) {
        const mounted = mount(host);

        mounted.controller.open({ view: 'tasks', project_id: 1 });
        await settle();

        return mounted;
    }

    it('shows each task with its description, status, priority, due date and the progress', async () => {
        await open();

        expect(titles()).toEqual(['Alpha', 'Beta', 'Gamma']);
        expect(text()).toContain('Draft and review');
        expect(text()).toContain('Overdue: ');
        expect(text()).toContain('1 of 3 completed (33%)');
        expect(control<HTMLSelectElement>('Status of Gamma').value).toBe('in_progress');
        expect(control<HTMLSelectElement>('Priority of Beta').value).toBe('low');
    });

    it('filters by status and priority without calling the server again', async () => {
        const { host } = await open();
        const loaded = host.calls().length;

        enter('Filter by priority', 'high');
        expect(titles()).toEqual(['Alpha', 'Gamma']);

        enter('Filter by status', 'in_progress');
        expect(titles()).toEqual(['Gamma']);

        enter('Filter by status', 'completed');
        expect(text()).toContain('No tasks match these filters');

        click('Clear filters');
        expect(titles()).toEqual(['Alpha', 'Beta', 'Gamma']);
        expect(host.calls()).toHaveLength(loaded);
    });

    it('keeps the filters while the user visits a task', async () => {
        await open();

        enter('Filter by status', 'completed');
        click('Beta');
        await settle();
        click('← Tasks');
        await settle();

        expect(titles()).toEqual(['Beta']);
        expect(control<HTMLSelectElement>('Filter by status').value).toBe('completed');
    });

    it('completes and reopens a task, then reloads the list and tells the model', async () => {
        const { host } = await open();

        control<HTMLButtonElement>('Complete Alpha').click();
        await settle();

        expect(host.calls('update_task')).toEqual([['update_task', { project_id: 1, task_id: 1, status: 'completed' }]]);
        expect(host.calls('list_tasks')).toHaveLength(2);
        expect(text()).toContain('2 of 3 completed');
        expect(host.bridge.updateContext).toHaveBeenLastCalledWith(expect.stringContaining('Changed task #1 "Alpha"'));

        control<HTMLButtonElement>('Reopen Alpha').click();
        await settle();

        expect(host.calls('update_task')[1][1]).toEqual({ project_id: 1, task_id: 1, status: 'todo' });
        expect(text()).toContain('1 of 3 completed');
    });

    it('changes status and priority directly', async () => {
        const { host } = await open();

        enter('Status of Alpha', 'in_progress');
        await settle();
        enter('Priority of Alpha', 'low');
        await settle();

        expect(host.calls('update_task').map(([, args]) => args)).toEqual([
            { project_id: 1, task_id: 1, status: 'in_progress' },
            { project_id: 1, task_id: 1, priority: 'low' },
        ]);
    });

    it('sends one request however often the control is used while saving', async () => {
        const host = fakeHost({ tasks: tasks() });
        const pending = deferred<ToolResult>();

        host.handlers.update_task = () => pending.promise;
        await open(host);

        const toggle = control<HTMLButtonElement>('Complete Alpha');

        toggle.click();
        toggle.click();
        control<HTMLButtonElement>('Complete Alpha').click();

        expect(host.calls('update_task')).toHaveLength(1);
        expect(isDisabled(control('Complete Alpha'))).toBe(true);
        expect(isDisabled(control('Status of Alpha'))).toBe(true);
        expect(isDisabled(control('Reopen Beta'))).toBe(false);
        expect(text()).toContain('Saving…');

        pending.resolve(refusal('Task not found.'));
        await settle();
        expect(isDisabled(control('Complete Alpha'))).toBe(false);
    });

    it('restores the real value and explains when a change is refused', async () => {
        const host = fakeHost({ tasks: tasks() });

        host.handlers.update_task = () => refusal('Task not found.');
        await open(host);

        enter('Status of Alpha', 'completed');
        await settle();

        expect(errors()).toContain('Task not found.');
        expect(control<HTMLSelectElement>('Status of Alpha').value).toBe('todo');
    });

    it('reloads and says what is known when a change is rejected by the host', async () => {
        const host = fakeHost({ tasks: tasks() });

        host.handlers.update_task = () => Promise.reject(new Error('401 token secret-value'));
        await open(host);

        control<HTMLButtonElement>('Complete Alpha').click();
        await settle();

        expect(notices()).toContain('Alpha: The change could not be confirmed. The current values are shown.');
        expect(errors()).toBe('');
        expect(text()).not.toContain('secret-value');
        expect(host.calls('list_tasks')).toHaveLength(2);
    });

    it('keeps the saved change when the reload fails', async () => {
        const host = fakeHost({ tasks: tasks() });

        await open(host);
        host.handlers.list_tasks = () => Promise.reject(new Error('offline'));
        control<HTMLButtonElement>('Complete Alpha').click();
        await settle();

        expect(text()).toContain('The list could not be refreshed');
        expect(control<HTMLSelectElement>('Status of Alpha').value).toBe('completed');
    });

    it('keeps one failure visible when another change succeeds', async () => {
        const host = fakeHost({ tasks: tasks() });
        const save = host.handlers.update_task;

        host.handlers.update_task = (args) => (args.task_id === 1 ? refusal('Task not found.') : save(args));
        await open(host);

        control<HTMLButtonElement>('Complete Alpha').click();
        await settle();
        control<HTMLButtonElement>('Reopen Beta').click();
        await settle();

        expect(errors()).toContain('Alpha: Task not found.');
        expect(text()).toContain('Task saved.');
    });

    it('does not let the user leave while a change is being saved', async () => {
        const host = fakeHost({ tasks: tasks() });
        const pending = deferred<ToolResult>();

        host.handlers.update_task = () => pending.promise;
        await open(host);
        control<HTMLButtonElement>('Complete Alpha').click();

        for (const label of ['← Launch plan', 'New task', 'Refresh', 'Gamma']) {
            expect(isDisabled(buttons(label)[0]), label).toBe(true);
            buttons(label)[0].click();
        }

        pending.resolve(serverError());
        await settle();

        expect(host.calls('get_task')).toHaveLength(0);
        expect(isDisabled(buttons('Gamma')[0])).toBe(false);
        expect(notices()).toContain('could not be confirmed');
    });

    it('also holds the task view while saving', async () => {
        const host = fakeHost({ tasks: tasks() });

        host.handlers.update_task = () => deferred<ToolResult>().promise;

        const { controller } = mount(host);

        controller.open({ view: 'task', project_id: 1, task_id: 1 });
        await settle();
        control<HTMLButtonElement>('Complete Alpha').click();

        for (const label of ['← Tasks', 'Edit task', 'Refresh']) {
            expect(isDisabled(buttons(label)[0]), label).toBe(true);
        }
    });

    it('reloads after an unexpected server error, as the change may have been saved', async () => {
        const host = fakeHost({ tasks: tasks() });
        const save = host.handlers.update_task;

        host.handlers.update_task = async (args) => {
            await save(args);

            return serverError();
        };
        await open(host);
        control<HTMLButtonElement>('Complete Alpha').click();
        await settle();

        expect(control<HTMLSelectElement>('Status of Alpha').value).toBe('completed');
    });

    it('keeps telling the model about every change as the view moves on', async () => {
        const { host } = await open();

        control<HTMLButtonElement>('Complete Alpha').click();
        await settle();
        control<HTMLButtonElement>('Reopen Beta').click();
        await settle();
        click('Gamma');
        await settle();

        const shared = host.bridge.updateContext.mock.lastCall![0];

        expect(shared).toContain('TaskNet view: task #3');
        expect(shared).toContain('Changed task #1 "Alpha"');
        expect(shared).toContain('Changed task #2 "Beta"');
    });

    it('has an empty state for a project without tasks', async () => {
        await open(fakeHost({ tasks: [] }));

        expect(text()).toContain('No tasks yet');
    });

    it('offers a retry when loading fails', async () => {
        const host = fakeHost({ tasks: tasks() });
        const original = host.handlers.list_tasks;

        host.handlers.list_tasks = () => Promise.reject(new Error('offline'));
        await open(host);
        expect(errors()).toContain('TaskNet did not answer this request.');

        host.handlers.list_tasks = original;
        click('Try again');
        await settle();
        expect(titles()).toEqual(['Alpha', 'Beta', 'Gamma']);
    });

    it('refuses a response with an unexpected shape', async () => {
        const host = fakeHost();

        host.handlers.list_tasks = () => ({ structuredContent: { tasks: [{ id: 'one' }] } });
        await open(host);

        expect(text()).toContain('could not read');
    });

    it('shows task details and updates them in place', async () => {
        const { host } = await open();

        click('Alpha');
        await settle();

        expect(text()).toContain('In Launch plan');
        expect(host.calls('get_task')).toEqual([['get_task', { project_id: 1, task_id: 1 }]]);

        control<HTMLButtonElement>('Complete Alpha').click();
        await settle();

        expect(control<HTMLSelectElement>('Status of Alpha').value).toBe('completed');
        expect(text()).toContain('Task saved.');
    });

    it('ignores a response that arrives after the user has moved on', async () => {
        const host = fakeHost();
        const slow = deferred<ToolResult>();

        host.handlers.list_projects = () => slow.promise;

        const { controller } = mount(host);

        controller.open({ view: 'projects' });
        controller.open({ view: 'project_form' });
        slow.resolve({ structuredContent: { projects: [project({ name: 'Too late' })] } });
        await settle();

        expect(text()).not.toContain('Too late');
        expect(host.bridge.updateContext).not.toHaveBeenCalledWith(expect.stringContaining('Too late'));
    });
});
