import type { TaskInput } from '../api';
import { h } from '../dom';
import { plural, quoted, taskStats } from '../format';
import { STATUS_LABELS } from '../rules';
import type { Priority, Project, Status, Task } from '../types';
import { alert, button, dueDate, emptyState, header, notice, priorityLevelChoices, progress, select, statusChoices } from '../ui';
import { loadingView } from './loader';
import { type Drafts, saveTask, taskControls, UNCONFIRMED_SHOWN } from './task-controls';
import { COMPACT_LIMIT, type View, type ViewContext } from './view';

interface Data {
    project: Project;
    tasks: Task[];
}

function summary({ project, tasks }: Data): string {
    const listed = tasks
        .slice(0, 20)
        .map((task) => `#${task.id} ${quoted(task.title)} (${STATUS_LABELS[task.status]})`)
        .join('; ');

    return `TaskNet view: tasks of project #${project.id} ${quoted(project.name)}, ${plural(tasks.length, 'task')}. ${listed}`;
}

export function taskListView(context: ViewContext, projectId: number): View {
    const saving = new Set<number>();
    const drafts: Drafts = new Map();
    // One entry per task, so a later success never hides an earlier problem.
    const problems = new Map<number, { text: string; failed: boolean }>();
    const filters = context.filters(projectId);
    let saved = false;
    let outOfDate = false;
    let reloads = 0;
    let newest: Promise<boolean>;

    const newTask = (): void => context.navigate({ view: 'task_form', project_id: projectId });

    /** Reload the list. Only the newest request is applied, and every caller learns how that one ended. */
    function reload(data: Data): Promise<boolean> {
        const own = ++reloads;

        newest = context.api
            .listTasks(projectId)
            .then(
                (tasks) => {
                    if (own === reloads) {
                        data.tasks = tasks;
                        outOfDate = false;
                    }

                    return true;
                },
                () => {
                    outOfDate ||= own === reloads;

                    return false;
                },
            )
            .then((loaded) => (own === reloads ? loaded : newest));

        return newest;
    }

    async function change(data: Data, task: Task, input: TaskInput): Promise<void> {
        if (saving.has(task.id)) {
            return;
        }

        saving.add(task.id);
        problems.delete(task.id);
        saved = false;
        context.dismissNotice();
        context.announce('Saving…');
        view.rerender();

        const outcome = await saveTask(context, task, input);

        if (outcome.kind === 'saved') {
            data.tasks = data.tasks.map((current) => (current.id === task.id ? outcome.task : current));
        }

        const needsReload = outcome.kind !== 'refused' || outcome.reload;
        const reloaded = needsReload && context.isCurrent() && (await reload(data));

        if (outcome.kind === 'saved') {
            saved = true;
        } else {
            const unconfirmed = outcome.kind === 'unconfirmed';
            const message = unconfirmed && reloaded ? UNCONFIRMED_SHOWN : outcome.message;

            problems.set(task.id, { text: `${task.title}: ${message}`, failed: !(unconfirmed && reloaded) });
        }

        saving.delete(task.id);

        if (context.isCurrent()) {
            view.rerender();
            context.publish(summary(data));

            const problem = problems.get(task.id);

            context.announce(problem?.text ?? 'Task saved.', problem?.failed);
        }
    }

    const view = loadingView<Data>(context, {
        load: async () => {
            const [project, tasks] = await Promise.all([context.api.getProject(projectId), context.api.listTasks(projectId)]);

            return { project, tasks };
        },
        fallback: { label: 'Projects', go: () => context.navigate({ view: 'projects' }) },
        loaded: (data) => context.publish(summary(data)),
        render(data, paint) {
            const { project, tasks } = data;
            const stats = taskStats(tasks, context.today());
            const matching = tasks.filter(
                (task) =>
                    (filters.status === '' || task.status === filters.status) &&
                    (filters.priority === '' || task.priority === filters.priority),
            );
            const shown = context.expanded() ? matching : matching.slice(0, COMPACT_LIMIT);
            const setFilter = (next: Partial<typeof filters>): void => {
                Object.assign(filters, next);
                view.rerender();
            };
            // Leaving while a change is in flight would lose its result, so navigation waits for it.
            const busy = saving.size > 0;

            paint(
                header('Tasks', {
                    back: { label: project.name, go: () => context.navigate({ view: 'project', project_id: projectId }), disabled: busy },
                    subtitle: plural(tasks.length, 'task'),
                    actions: [
                        button('New task', newTask, { primary: true, disabled: busy }),
                        button('Refresh', () => context.navigate({ view: 'tasks', project_id: projectId }), { disabled: busy }),
                    ],
                }),
                ...[...problems.values()].map(({ text, failed }) => (failed ? alert(text) : notice(text))),
                outOfDate && alert('The list could not be refreshed and may be out of date.'),
                saved && notice('Task saved.'),
                tasks.length > 0 && progress(stats.completed, stats.total),
                tasks.length > 0 &&
                    h(
                        'div',
                        { class: 'tn-controls', role: 'group', 'aria-label': 'Filter tasks' },
                        select<Status | ''>(
                            'Filter by status',
                            filters.status,
                            [['', 'All statuses'], ...statusChoices],
                            (status) => setFilter({ status }),
                            { key: 'filter-status' },
                        ),
                        select<Priority | ''>(
                            'Filter by priority',
                            filters.priority,
                            [['', 'All priorities'], ...priorityLevelChoices],
                            (priority) => setFilter({ priority }),
                            { key: 'filter-priority' },
                        ),
                    ),
                tasks.length === 0 && emptyState('No tasks yet', 'Add the first task to this project.', button('Add a task', newTask)),
                tasks.length > 0 &&
                    matching.length === 0 &&
                    emptyState(
                        'No tasks match these filters',
                        'Change or clear the filters to see more.',
                        button('Clear filters', () => {
                            paint.focusNext('filter-status');
                            setFilter({ status: '', priority: '' });
                        }),
                    ),
                shown.length > 0 &&
                    h(
                        'ul',
                        { class: 'tn-list' },
                        ...shown.map((task) =>
                            h(
                                'li',
                                { class: 'tn-item' },
                                h(
                                    'div',
                                    { class: 'tn-header-row' },
                                    button(
                                        task.title,
                                        () => context.navigate({ view: 'task', project_id: projectId, task_id: task.id }),
                                        { link: true, key: `task-${task.id}`, disabled: busy },
                                    ),
                                    dueDate(task, context.today(), context.locale()),
                                ),
                                task.description && h('p', { class: 'tn-muted tn-clamp' }, task.description),
                                taskControls(
                                    task,
                                    saving.has(task.id),
                                    drafts,
                                    (input) => void change(data, task, input),
                                    () => context.announce('Change not saved.'),
                                ),
                            ),
                        ),
                    ),
                shown.length < matching.length &&
                    button(
                        `Show all ${matching.length} tasks`,
                        () => {
                            paint.focusNext(`task-${matching[shown.length].id}`);
                            context.expand();
                        },
                        { key: 'show-all' },
                    ),
            );
        },
    });

    return view;
}
