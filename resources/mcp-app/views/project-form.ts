import { h } from '../dom';
import { type CheckResult, createForm, textField } from '../forms';
import { quoted } from '../format';
import { LIMITS } from '../rules';
import type { Project, Route } from '../types';
import { header } from '../ui';
import { loadingView } from './loader';
import { recordingAttempt, type View, type ViewContext } from './view';

/**
 * After an unconfirmed creation, look for a project with exactly what was sent. An older one can look the
 * same, so its creation time is shown and the user decides.
 */
async function findCreated(context: ViewContext, sent: Record<string, string>): Promise<CheckResult> {
    const newest = (await context.api.listProjects())
        .filter((project) => project.name === sent.name && (project.description ?? '') === sent.description)
        .at(-1);

    if (!newest) {
        return {
            message: 'No project with these details was found, so it does not appear to have been saved. You can send it again.',
            note: `Checked: no project matching ${quoted(sent.name)} was found.`,
        };
    }

    const created = newest.created_at ? `, created ${context.dateTime(newest.created_at)}` : '';

    return {
        message:
            `A project with these details exists (#${newest.id}${created}). If that is when you sent this, it was saved. ` +
            'If it is older, you can send it again.',
        note: `Checked: a project named ${quoted(sent.name)} with the same details exists (#${newest.id}${created}).`,
        open: { label: 'Open it', go: () => context.navigate({ view: 'project', project_id: newest.id }) },
    };
}

function buildForm(context: ViewContext, project?: Project): HTMLElement {
    const leave: Route = project ? { view: 'project', project_id: project.id } : { view: 'projects' };

    const saved = (result: Project, message: string): void => {
        context.recordChange(`${project ? 'Updated' : 'Created'} project #${result.id} ${quoted(result.name)}.`);

        if (context.isCurrent()) {
            context.navigate({ view: 'project', project_id: result.id }, message);
        }
    };

    return createForm({
        fields: [
            textField({ name: 'name', label: 'Name', value: project?.name, max: LIMITS.project.name, required: true }),
            textField({
                name: 'description',
                label: 'Description (optional)',
                value: project?.description ?? '',
                max: LIMITS.project.description,
                multiline: true,
            }),
        ],
        submitLabel: project ? 'Save changes' : 'Create project',
        cancel: () => context.navigate(leave),
        announce: (message, urgent) => context.announce(message, urgent),
        duplicateRisk: project
            ? undefined
            : {
                  check: (values) => findCreated(context, values),
                  // An answer that arrives after the user left the form is of no interest.
                  record: (note) => context.isCurrent() && context.recordChange(note),
              },
        async save(values, changes) {
            if (!project) {
                const created = await recordingAttempt(context, `create a project named ${quoted(values.name)}`, () =>
                    context.api.createProject(values),
                );

                saved(created, 'Project created.');

                return;
            }

            // Only what changed is sent, so untouched fields are never overwritten.
            if (Object.keys(changes).length === 0) {
                context.navigate(leave, 'No changes to save.');

                return;
            }

            const updated = await recordingAttempt(context, `update project #${project.id} ${quoted(project.name)}`, () =>
                context.api.updateProject(project.id, changes),
            );

            saved(updated, 'Project saved.');
        },
    });
}

/** Opening this view only reads. Nothing is written until the user submits. */
export function projectFormView(context: ViewContext, projectId?: number): View {
    if (projectId === undefined) {
        context.publish('TaskNet view: a form for a new project. Opening it changes nothing.');

        return {
            element: h('section', { class: 'tn-view' }, header('New project'), buildForm(context)),
            rerender() {},
        };
    }

    let form: HTMLElement | undefined;

    return loadingView(context, {
        load: () => context.api.getProject(projectId),
        fallback: { label: 'Projects', go: () => context.navigate({ view: 'projects' }) },
        loaded: (project) =>
            context.publish(`TaskNet view: the edit form for project #${project.id} ${quoted(project.name)}. Opening it changes nothing.`),
        render(project, paint) {
            // Built once: redrawing would discard what the user has typed.
            if (!form) {
                form = buildForm(context, project);
                paint(header('Edit project'), form);
            }
        },
    });
}
