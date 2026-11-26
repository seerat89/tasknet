import { h } from '../dom';
import { plural, quoted } from '../format';
import { button, emptyState, header } from '../ui';
import { loadingView } from './loader';
import { COMPACT_LIMIT, type View, type ViewContext } from './view';

export function projectListView(context: ViewContext): View {
    const create = (): void => context.navigate({ view: 'project_form' });

    return loadingView(context, {
        load: () => context.api.listProjects(),
        loaded(projects) {
            const summary = projects.map((project) => `#${project.id} ${quoted(project.name)} (${plural(project.tasks_count ?? 0, 'task')})`);

            context.publish(`TaskNet view: project list, ${plural(projects.length, 'project')}. ${summary.join('; ')}`);
        },
        render(projects, paint) {
            const shown = context.expanded() ? projects : projects.slice(0, COMPACT_LIMIT);

            paint(
                header('Projects', {
                    subtitle: plural(projects.length, 'project'),
                    actions: [
                        button('New project', create, { primary: true }),
                        button('Refresh', () => context.navigate({ view: 'projects' })),
                    ],
                }),
                projects.length === 0
                    ? emptyState('No projects yet', 'Create your first project to start adding tasks.', button('Create a project', create))
                    : h(
                          'ul',
                          { class: 'tn-list' },
                          ...shown.map((project) =>
                              h(
                                  'li',
                                  { class: 'tn-item' },
                                  h(
                                      'div',
                                      { class: 'tn-header-row' },
                                      button(project.name, () => context.navigate({ view: 'project', project_id: project.id }), {
                                          link: true,
                                          key: `project-${project.id}`,
                                      }),
                                      h('span', { class: 'tn-badge' }, plural(project.tasks_count ?? 0, 'task')),
                                  ),
                                  h('p', { class: 'tn-muted tn-clamp' }, project.description || 'No description'),
                              ),
                          ),
                      ),
                shown.length < projects.length &&
                    button(
                        `Show all ${projects.length} projects`,
                        () => {
                            paint.focusNext(`project-${projects[shown.length].id}`);
                            context.expand();
                        },
                        { key: 'show-all' },
                    ),
            );
        },
    });
}
