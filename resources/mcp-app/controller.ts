import type { McpUiHostContext } from '@modelcontextprotocol/ext-apps';
import { createApi } from './api';
import { h, swap } from './dom';
import { formatDateTime, today } from './format';
import { type HostBridge, type Route, routeSchema, type ToolResult } from './types';
import { alert, button, loading, notice } from './ui';
import { projectDetailView } from './views/project-detail';
import { projectFormView } from './views/project-form';
import { projectListView } from './views/project-list';
import { taskDetailView } from './views/task-detail';
import { taskFormView } from './views/task-form';
import { taskListView } from './views/task-list';
import type { TaskFilters, View, ViewContext } from './views/view';

export interface Controller {
    open(route: Route): void;
    handleToolResult(result: ToolResult): void;
    handleCancelled(): void;
    handleConnectionFailure(): void;
    setHostContext(context: McpUiHostContext): void;
}

const RECENT_CHANGES = 10;

function createView(route: Route, context: ViewContext): View {
    switch (route.view) {
        case 'projects':
            return projectListView(context);
        case 'project':
            return projectDetailView(context, route.project_id);
        case 'tasks':
            return taskListView(context, route.project_id);
        case 'task':
            return taskDetailView(context, route.project_id, route.task_id);
        case 'project_form':
            return projectFormView(context, route.project_id);
        case 'task_form':
            return taskFormView(context, route.project_id, route.task_id);
    }
}

/**
 * A live region that stays in the page for good, so screen readers notice when its text changes.
 * The text is cleared first, so the same message can be spoken twice.
 */
function liveRegion(role: 'status' | 'alert') {
    const element = h('p', { class: 'sr-only', role });
    let pending: ReturnType<typeof setTimeout> | undefined;

    const say = (message: string): void => {
        clearTimeout(pending);
        element.textContent = '';

        if (message !== '') {
            pending = setTimeout(() => (element.textContent = message), 0);
        }
    };

    return { element, say };
}

/** Owns which view is on screen and what survives between views. */
export function createController(root: HTMLElement, bridge: HostBridge): Controller {
    const api = createApi(bridge);
    const filters = new Map<number, TaskFilters>();
    const content = h('div', { class: 'tn-root', tabindex: -1 }, loading('Opening TaskNet…'));
    const polite = liveRegion('status');
    const urgent = liveRegion('alert');
    let view: View | undefined;
    let generation = 0;
    let host: McpUiHostContext = {};
    let userExpanded = false;
    let showing = '';
    const changes: string[] = [];

    root.replaceChildren(content, polite.element, urgent.element);

    const announce = (message: string, isUrgent = false): void => (isUrgent ? urgent : polite).say(message);

    const silence = (): void => {
        polite.say('');
        urgent.say('');
    };

    // The host keeps only the latest context, so every update carries the view and the recent changes.
    const share = (): void => {
        const history = changes.length === 0 ? '' : `What the user changed or checked in this TaskNet view, oldest first: ${changes.join(' ')}`;

        bridge.updateContext([showing, history].filter(Boolean).join('\n'));
    };

    const open = (route: Route, message?: string): void => {
        const own = ++generation;
        const arrival = message === undefined ? undefined : notice(message);

        silence();

        if (message !== undefined) {
            announce(message);
        }

        view = createView(route, {
            api,
            navigate(next, note) {
                open(next, note);
                // Keyboard users continue from the top of the new view.
                content.focus({ preventScroll: true });
            },
            isCurrent: () => own === generation,
            expanded: () => userExpanded || host.displayMode === 'fullscreen',
            expand() {
                userExpanded = true;
                view?.rerender();

                if (host.displayMode !== 'fullscreen' && bridge.canFullscreen()) {
                    void bridge.requestFullscreen();
                }
            },
            today: () => today(host.timeZone),
            locale: () => host.locale,
            dateTime: (timestamp) => formatDateTime(timestamp, host.locale, host.timeZone),
            filters(projectId) {
                const current = filters.get(projectId) ?? { status: '', priority: '' };

                filters.set(projectId, current);

                return current;
            },
            publish(text) {
                showing = text;
                share();
            },
            recordChange(text) {
                changes.push(text);
                changes.splice(0, changes.length - RECENT_CHANGES);
                share();
            },
            announce(note, isUrgent) {
                if (own === generation) {
                    announce(note, isUrgent);
                }
            },
            dismissNotice: () => arrival?.remove(),
        });

        swap(content, ...(arrival ? [arrival] : []), view.element);
    };

    const fail = (message: string): void => {
        generation++;
        view = undefined;
        showing = 'TaskNet view: this view could not be opened.';
        share();
        swap(
            content,
            alert(
                message,
                button('Browse projects', () => {
                    open({ view: 'projects' });
                    content.focus({ preventScroll: true });
                }),
            ),
        );
        announce(message, true);
    };

    return {
        open: (route) => open(route),

        handleToolResult(result) {
            if (result.isError) {
                const text = result.content?.find((block) => block.type === 'text')?.text;

                fail(text ?? 'TaskNet could not open this view.');

                return;
            }

            const route = routeSchema.safeParse(result.structuredContent);

            if (route.success) {
                open(route.data);
            } else {
                fail('TaskNet could not open this view.');
            }
        },

        handleCancelled() {
            if (view === undefined) {
                fail('Opening TaskNet was cancelled.');
            }
        },

        handleConnectionFailure() {
            const message = 'This view could not connect to your AI client. Ask for TaskNet again in the chat.';

            swap(content, alert(message));
            announce(message, true);
        },

        setHostContext(next) {
            const changed =
                next.displayMode !== host.displayMode || next.timeZone !== host.timeZone || next.locale !== host.locale;

            if (host.displayMode === 'fullscreen' && next.displayMode === 'inline') {
                userExpanded = false;
            }

            // Copied, because the SDK may update its context object in place.
            host = { ...next };

            if (changed) {
                view?.rerender();
            }
        },
    };
}
