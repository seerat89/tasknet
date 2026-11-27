import type { TaskInput } from '../api';
import { h } from '../dom';
import { quoted } from '../format';
import { PRIORITY_LABELS, STATUS_LABELS } from '../rules';
import type { Project, Task } from '../types';
import { alert, button, dueDate, header, notice } from '../ui';
import { loadingView } from './loader';
import { type Drafts, saveTask, taskControls, UNCONFIRMED_SHOWN } from './task-controls';
import type { View, ViewContext } from './view';

interface Data {
    project: Project;
    task: Task;
}

function summary({ project, task }: Data): string {
    return (
        `TaskNet view: task #${task.id} ${quoted(task.title)} in project #${project.id} ${quoted(project.name)}, ` +
        `status ${STATUS_LABELS[task.status]}, priority ${PRIORITY_LABELS[task.priority]}.`
    );
}

export function taskDetailView(context: ViewContext, projectId: number, taskId: number): View {
    const drafts: Drafts = new Map();
    let saving = false;
    let feedback: { text: string; failed: boolean } | undefined;
    /** Set when TaskNet refused a change and the task can no longer be read. */
    let gone = false;

    const toTasks = (): void => context.navigate({ view: 'tasks', project_id: projectId });

    async function change(data: Data, input: TaskInput): Promise<void> {
        if (saving) {
            return;
        }

        saving = true;
        feedback = undefined;
        context.dismissNotice();
        context.announce('Saving…');
        view.rerender();

        const outcome = await saveTask(context, data.task, input);
        let reloaded = false;

        if (outcome.kind === 'saved') {
            data.task = outcome.task;
        } else if ((outcome.kind === 'unconfirmed' || outcome.reload) && context.isCurrent()) {
            try {
                data.task = await context.api.getTask(projectId, taskId);
                reloaded = true;
            } catch {
                gone = outcome.kind === 'refused';
            }
        }

        if (outcome.kind === 'saved') {
            feedback = { text: 'Task saved.', failed: false };
        } else if (outcome.kind === 'refused') {
            feedback = { text: outcome.message, failed: true };
        } else {
            feedback = reloaded
                ? { text: UNCONFIRMED_SHOWN, failed: false }
                : { text: `${outcome.message} The current values could not be loaded.`, failed: true };
        }

        saving = false;
        drafts.delete(taskId);

        if (context.isCurrent()) {
            view.rerender();
            context.publish(gone ? `TaskNet view: task #${taskId} is no longer available.` : summary(data));
            context.announce(feedback.text, feedback.failed);
        }
    }

    const view = loadingView<Data>(context, {
        load: async () => {
            const [project, task] = await Promise.all([context.api.getProject(projectId), context.api.getTask(projectId, taskId)]);

            return { project, task };
        },
        fallback: { label: 'Projects', go: () => context.navigate({ view: 'projects' }) },
        loaded: (data) => context.publish(summary(data)),
        render(data, paint) {
            const { project, task } = data;
            const message = feedback && (feedback.failed ? alert(feedback.text) : notice(feedback.text));

            if (gone) {
                paint(
                    header('Task unavailable', { back: { label: 'Tasks', go: toTasks } }),
                    message,
                    h('p', {}, 'This task is no longer available. It may have been deleted.'),
                );

                return;
            }

            paint(
                header(task.title, {
                    // Leaving while a change is in flight would lose its result, so navigation waits for it.
                    back: { label: 'Tasks', go: toTasks, disabled: saving },
                    subtitle: `In ${project.name}`,
                    actions: [
                        button('Edit task', () => context.navigate({ view: 'task_form', project_id: projectId, task_id: taskId }), {
                            disabled: saving,
                        }),
                        button('Refresh', () => context.navigate({ view: 'task', project_id: projectId, task_id: taskId }), {
                            disabled: saving,
                        }),
                    ],
                }),
                message,
                h('p', { class: 'tn-body' }, task.description || 'No description'),
                h('p', {}, dueDate(task, context.today(), context.locale())),
                taskControls(
                    task,
                    saving,
                    drafts,
                    (input) => void change(data, input),
                    (message) => context.announce(message),
                ),
            );
        },
    });

    return view;
}
