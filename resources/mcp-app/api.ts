import * as z from 'zod';
import { type HostBridge, type Project, projectSchema, type Task, taskSchema, type ToolResult } from './types';

export const VALIDATION_ERRORS_KEY = 'tasknet/validationErrors';
export const REFUSED_KEY = 'tasknet/refused';

// A rejected call can be a rate limit, a network fault or lost access, and hosts do not say which.
export const UNREACHABLE_MESSAGE =
    'TaskNet did not answer this request. Wait a moment and try again. If it keeps happening, reconnect TaskNet ' +
    'in your AI client, or start a new demo workspace if yours has expired.';

/**
 * refused: TaskNet said it rejected the request, so nothing changed.
 * unconfirmed: no usable answer or an unexpected server error, so a write may or may not have happened.
 */
export class ToolError extends Error {
    constructor(
        message: string,
        readonly kind: 'refused' | 'unconfirmed',
        readonly fieldErrors: Record<string, string> = {},
    ) {
        super(message);
        this.name = 'ToolError';
    }
}

const fieldErrorsSchema = z.record(z.string(), z.array(z.string()).min(1));

function errorText(result: ToolResult): string {
    const text = (result.content ?? [])
        .map((block) => (block.type === 'text' ? (block.text ?? '') : ''))
        .join(' ')
        .trim();

    return text === '' ? 'TaskNet could not complete the request.' : text;
}

function fieldErrors(result: ToolResult): Record<string, string> {
    const parsed = fieldErrorsSchema.safeParse(result._meta?.[VALIDATION_ERRORS_KEY]);

    return parsed.success ? Object.fromEntries(Object.entries(parsed.data).map(([field, messages]) => [field, messages.join(' ')])) : {};
}

export type ProjectInput = { name?: string; description?: string };
export type TaskInput = { title?: string; description?: string; status?: string; priority?: string; due_date?: string };

/** Typed access to the eight TaskNet data tools. The widget has no other way to read or change data. */
export function createApi(bridge: HostBridge) {
    async function call<T>(name: string, args: Record<string, unknown>, schema: z.ZodType<T>): Promise<T> {
        let result: ToolResult;

        try {
            result = await bridge.callTool(name, args);
        } catch {
            throw new ToolError(UNREACHABLE_MESSAGE, 'unconfirmed');
        }

        if (result.isError) {
            const fields = fieldErrors(result);
            const refused = Object.keys(fields).length > 0 || result._meta?.[REFUSED_KEY] === true;

            throw new ToolError(errorText(result), refused ? 'refused' : 'unconfirmed', fields);
        }

        const parsed = schema.safeParse(result.structuredContent);

        if (!parsed.success) {
            throw new ToolError('TaskNet sent a response this view could not read. Refresh to see the current data.', 'unconfirmed');
        }

        return parsed.data;
    }

    const project = z.object({ project: projectSchema });
    const task = z.object({ task: taskSchema });

    return {
        listProjects: async (): Promise<Project[]> =>
            (await call('list_projects', {}, z.object({ projects: z.array(projectSchema) }))).projects,
        getProject: async (projectId: number): Promise<Project> => (await call('get_project', { project_id: projectId }, project)).project,
        createProject: async (input: ProjectInput): Promise<Project> => (await call('create_project', input, project)).project,
        updateProject: async (projectId: number, input: ProjectInput): Promise<Project> =>
            (await call('update_project', { ...input, project_id: projectId }, project)).project,
        listTasks: async (projectId: number): Promise<Task[]> =>
            (await call('list_tasks', { project_id: projectId }, z.object({ tasks: z.array(taskSchema) }))).tasks,
        getTask: async (projectId: number, taskId: number): Promise<Task> =>
            (await call('get_task', { project_id: projectId, task_id: taskId }, task)).task,
        createTask: async (projectId: number, input: TaskInput): Promise<Task> =>
            (await call('create_task', { ...input, project_id: projectId }, task)).task,
        updateTask: async (projectId: number, taskId: number, input: TaskInput): Promise<Task> =>
            (await call('update_task', { ...input, project_id: projectId, task_id: taskId }, task)).task,
    };
}

export type Api = ReturnType<typeof createApi>;
