import * as z from 'zod';
import { PRIORITIES, STATUSES } from './rules';

// The widget's CSP forbids eval, which zod would otherwise probe for.
z.config({ jitless: true });

const id = z.number().int().positive();

export const projectSchema = z.object({
    id,
    name: z.string(),
    description: z.string().nullable(),
    tasks_count: z.number().int().nonnegative().optional(),
    created_at: z.string().nullable().optional(),
});

export const taskSchema = z.object({
    id,
    project_id: id,
    title: z.string(),
    description: z.string().nullable(),
    status: z.enum(STATUSES),
    priority: z.enum(PRIORITIES),
    due_date: z.string().nullable(),
    created_at: z.string().nullable().optional(),
});

/** What the show_* tools return: where to start. All data is then loaded through the data tools. */
export const routeSchema = z.discriminatedUnion('view', [
    z.object({ view: z.literal('projects') }),
    z.object({ view: z.literal('project'), project_id: id }),
    z.object({ view: z.literal('tasks'), project_id: id }),
    z.object({ view: z.literal('task'), project_id: id, task_id: id }),
    z.object({ view: z.literal('project_form'), project_id: id.optional() }),
    z.object({ view: z.literal('task_form'), project_id: id.optional(), task_id: id.optional() }),
]);

export type Status = (typeof STATUSES)[number];
export type Priority = (typeof PRIORITIES)[number];
export type Project = z.infer<typeof projectSchema>;
export type Task = z.infer<typeof taskSchema>;
export type Route = z.infer<typeof routeSchema>;

export interface ToolResult {
    content?: Array<{ type: string; text?: string }>;
    structuredContent?: unknown;
    isError?: boolean;
    _meta?: Record<string, unknown>;
}

export interface HostBridge {
    callTool(name: string, args: Record<string, unknown>): Promise<ToolResult>;
    updateContext(text: string): void;
    canFullscreen(): boolean;
    requestFullscreen(): Promise<boolean>;
}
