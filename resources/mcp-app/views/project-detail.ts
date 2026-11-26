import { h } from '../dom';
import { quoted, taskStats } from '../format';
import { button, emptyState, header, progress } from '../ui';
import { loadingView } from './loader';
import type { View, ViewContext } from './view';

export function projectDetailView(context: ViewContext, projectId: number): View {
    const newTask = (): void => context.navigate({ view: 'task_form', project_id: projectId });

    return loadingView(context, {
        load: async () => {
            const [project, tasks] = await Promise.all([context.api.getProject(projectId), context.api.listTasks(projectId)]);

            return { project, tasks };
        },
        fallback: { label: 'Projects', go: () => context.navigate({ view: 'projects' }) },
        loaded({ project, tasks }) {
            const stats = taskStats(tasks, context.today());

            context.publish(
                `TaskNet view: project #${project.id} ${quoted(project.name)}. Tasks: ${stats.total}, completed ${stats.completed}, ` +
                    `in progress ${stats.inProgress}, todo ${stats.pending}, overdue ${stats.overdue}.`,
            );
        },
        render({ project, tasks }, paint) {
            const stats = taskStats(tasks, context.today());
            const figures: Array<[string, number]> = [
                ['Total', stats.total],
                ['Completed', stats.completed],
                ['In progress', stats.inProgress],
                ['Pending', stats.pending],
                ['Overdue', stats.overdue],
            ];

            paint(
                header(project.name, {
                    back: { label: 'Projects', go: () => context.navigate({ view: 'projects' }) },
                    actions: [
                        button('Edit project', () => context.navigate({ view: 'project_form', project_id: project.id })),
                        button('New task', newTask),
                    ],
                }),
                h('p', { class: 'tn-body' }, project.description || 'No description'),
                tasks.length === 0
                    ? emptyState('No tasks yet', 'Add a task to start tracking progress.', button('Add a task', newTask))
                    : h(
                          'div',
                          { class: 'grid gap-3' },
                          h(
                              'dl',
                              { class: 'tn-stats' },
                              ...figures.map(([label, value]) =>
                                  h(
                                      'div',
                                      { class: label === 'Overdue' && value > 0 ? 'tn-stat tn-stat-warning' : 'tn-stat' },
                                      h('dt', { class: 'tn-muted' }, label),
                                      h('dd', { class: 'tn-figure' }, value),
                                  ),
                              ),
                          ),
                          progress(stats.completed, stats.total),
                          button('View tasks', () => context.navigate({ view: 'tasks', project_id: project.id }), { primary: true }),
                      ),
            );
        },
    });
}
