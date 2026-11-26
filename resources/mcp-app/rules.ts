/**
 * The values the widget shares with the server, kept in one place.
 *
 * The server validates every write and has the final say. These copies only give earlier feedback, and
 * tests/Feature/McpAppRulesTest.php fails if they drift from the PHP rules and enums named below.
 */

/** App\Http\Requests\StoreProjectRequest and StoreTaskRequest: maximum characters per field. */
export const LIMITS = {
    project: { name: 120, description: 2000 },
    task: { title: 160, description: 5000 },
} as const;

/** App\Enums\TaskStatus, in the order of its cases. */
export const STATUSES = ['todo', 'in_progress', 'completed'] as const;
export const STATUS_LABELS: Record<(typeof STATUSES)[number], string> = { todo: 'Todo', in_progress: 'In progress', completed: 'Completed' };

/** App\Enums\TaskPriority, in the order of its cases. */
export const PRIORITIES = ['low', 'medium', 'high'] as const;
export const PRIORITY_LABELS: Record<(typeof PRIORITIES)[number], string> = { low: 'Low', medium: 'Medium', high: 'High' };
