import { type CheckResult, createForm, dateField, type Field, selectField, textField } from '../forms';
import { quoted } from '../format';
import { LIMITS } from '../rules';
import type { Project, Route, Task } from '../types';
import { button, emptyState, header, priorityChoices, statusChoices } from '../ui';
import { loadingView } from './loader';
import { recordingAttempt, type View, type ViewContext } from './view';

function commonFields(task?: Task): Field[] {
    return [
        textField({ name: 'title', label: 'Title', value: task?.title, max: LIMITS.task.title, required: true }),
        textField({
            name: 'description',
            label: 'Description (optional)',
            value: task?.description ?? '',
            max: LIMITS.task.description,
            multiline: true,
        }),
        selectField({ name: 'status', label: 'Status', value: task?.status ?? 'todo', choices: statusChoices }),
        selectField({ name: 'priority', label: 'Priority', value: task?.priority ?? 'medium', choices: priorityChoices }),
        dateField({ name: 'due_date', label: 'Due date (optional)', value: task?.due_date ?? '' }),
    ];
}

function saved(context: ViewContext, task: Task, verb: 'Created' | 'Updated', message: string): void {
    context.recordChange(`${verb} task #${task.id} ${quoted(task.title)} in project #${task.project_id}.`);

    if (context.isCurrent()) {
        context.navigate({ view: 'task', project_id: task.project_id, task_id: task.id }, message);
    }
}

/**
 * After an unconfirmed creation, look in the chosen project for a task with exactly what was sent. An
 * older one can look the same, so its creation time is shown and the user decides.
 */
async function findCreated(context: ViewContext, sent: Record<string, string>): Promise<CheckResult> {
    const projectId = Number(sent.project_id);
    const newest = (await context.api.listTasks(projectId))
        .filter(
            (task) =>
                task.title === sent.title &&
                (task.description ?? '') === sent.description &&
                task.status === sent.status &&
                task.priority === sent.priority &&
                (task.due_date ?? '') === sent.due_date,
        )
        .at(-1);

    if (!newest) {
        return {
            message: 'No task with these details was found, so it does not appear to have been saved. You can send it again.',
            note: `Checked: no task matching ${quoted(sent.title)} was found in project #${projectId}.`,
        };
    }

    const created = newest.created_at ? `, created ${context.dateTime(newest.created_at)}` : '';

    return {
        message:
            `A task with these details exists in this project (#${newest.id}${created}). If that is when you sent this, ` +
            'it was saved. If it is older, you can send it again.',
        note: `Checked: a task titled ${quoted(sent.title)} with the same details exists in project #${projectId} (#${newest.id}${created}).`,
        open: { label: 'Open it', go: () => context.navigate({ view: 'task', project_id: projectId, task_id: newest.id }) },
    };
}

function createTaskView(context: ViewContext, projectId?: number): View {
    const leave: Route = projectId === undefined ? { view: 'projects' } : { view: 'tasks', project_id: projectId };
    let built = false;

    return loadingView(context, {
        load: () => context.api.listProjects(),
        fallback: { label: 'Projects', go: () => context.navigate({ view: 'projects' }) },
        loaded: () => context.publish('TaskNet view: a form for a new task. Opening it changes nothing.'),
        render(projects: Project[], paint) {
            if (built) {
                return;
            }

            built = true;

            if (projects.length === 0) {
                paint(
                    header('New task'),
                    emptyState(
                        'Create a project first',
                        'Every task belongs to a project.',
                        button('Create a project', () => context.navigate({ view: 'project_form' })),
                    ),
                );

                return;
            }

            const preselected = projects.some((project) => project.id === projectId)
                ? String(projectId)
                : projects.length === 1
                  ? String(projects[0].id)
                  : '';

            paint(
                header('New task'),
                createForm({
                    fields: [
                        selectField({
                            name: 'project_id',
                            label: 'Project',
                            value: preselected,
                            choices: [['', 'Choose a project'], ...projects.map((project) => [String(project.id), project.name] as const)],
                        }),
                        ...commonFields(),
                    ],
                    submitLabel: 'Create task',
                    cancel: () => context.navigate(leave),
                    announce: (message, urgent) => context.announce(message, urgent),
                    duplicateRisk: {
                        check: (values) => findCreated(context, values),
                        // An answer that arrives after the user left the form is of no interest.
                        record: (note) => context.isCurrent() && context.recordChange(note),
                    },
                    async save({ project_id, due_date, ...values }) {
                        const task = await recordingAttempt(
                            context,
                            `create a task titled ${quoted(values.title)} in project #${project_id}`,
                            () =>
                                context.api.createTask(Number(project_id), {
                                    ...values,
                                    ...(due_date === '' ? {} : { due_date }),
                                }),
                        );

                        saved(context, task, 'Created', 'Task created.');
                    },
                }),
            );
        },
    });
}

function editTaskView(context: ViewContext, projectId: number, taskId: number): View {
    const leave: Route = { view: 'task', project_id: projectId, task_id: taskId };
    let built = false;

    return loadingView(context, {
        load: async () => {
            const [project, task] = await Promise.all([context.api.getProject(projectId), context.api.getTask(projectId, taskId)]);

            return { project, task };
        },
        fallback: { label: 'Projects', go: () => context.navigate({ view: 'projects' }) },
        loaded: ({ task }) =>
            context.publish(`TaskNet view: the edit form for task #${task.id} ${quoted(task.title)}. Opening it changes nothing.`),
        render({ project, task }, paint) {
            if (built) {
                return;
            }

            built = true;

            paint(
                header('Edit task', { subtitle: `In ${project.name}` }),
                createForm({
                    fields: commonFields(task),
                    submitLabel: 'Save changes',
                    cancel: () => context.navigate(leave),
                    announce: (message, urgent) => context.announce(message, urgent),
                    async save(_values, changes) {
                        // Only what changed is sent, so untouched fields are never overwritten.
                        if (Object.keys(changes).length === 0) {
                            context.navigate(leave, 'No changes to save.');

                            return;
                        }

                        const updated = await recordingAttempt(context, `update task #${task.id} ${quoted(task.title)}`, () =>
                            context.api.updateTask(projectId, taskId, changes),
                        );

                        saved(context, updated, 'Updated', 'Task saved.');
                    },
                }),
            );
        },
    });
}

/** Opening this view only reads. Nothing is written until the user submits. */
export function taskFormView(context: ViewContext, projectId?: number, taskId?: number): View {
    return projectId !== undefined && taskId !== undefined
        ? editTaskView(context, projectId, taskId)
        : createTaskView(context, projectId);
}
