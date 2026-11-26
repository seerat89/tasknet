import { type Api, ToolError } from '../api';
import type { Priority, Route, Status } from '../types';

export interface TaskFilters {
    status: Status | '';
    priority: Priority | '';
}

export interface ViewContext {
    api: Api;
    navigate(route: Route, notice?: string): void;
    /** False once the user has moved on, so late responses are ignored. */
    isCurrent(): boolean;
    /** Whether the view may show everything rather than a compact inline summary. */
    expanded(): boolean;
    expand(): void;
    today(): string;
    locale(): string | undefined;
    /** A server timestamp in the viewer's locale and time zone. */
    dateTime(timestamp: string): string;
    filters(projectId: number): TaskFilters;
    /** Tell the model what the user is looking at. */
    publish(text: string): void;
    /** Tell the model what the user changed or checked. Kept alongside later views, as each update replaces the last. */
    recordChange(text: string): void;
    /** Speak a message to screen reader users. Urgent messages interrupt. */
    announce(message: string, urgent?: boolean): void;
    /** Remove the message shown on arrival, such as "Task saved.", before something new happens. */
    dismissNotice(): void;
}

export interface View {
    element: HTMLElement;
    /** Redraw after the display mode or host context changes. */
    rerender(): void;
}

export const COMPACT_LIMIT = 5;

export function messageOf(error: unknown): string {
    return error instanceof ToolError ? error.message : 'Something went wrong in this view. Try again.';
}

/** Run a write, and tell the model when TaskNet does not confirm whether it happened. */
export async function recordingAttempt<T>(context: ViewContext, attempt: string, write: () => Promise<T>): Promise<T> {
    try {
        return await write();
    } catch (error) {
        if (!(error instanceof ToolError && error.kind === 'refused')) {
            context.recordChange(`Tried to ${attempt}; TaskNet did not confirm it, so it may or may not have been saved.`);
        }

        throw error;
    }
}
