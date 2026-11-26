import type { Status, Task } from './types';

/** Today's date as YYYY-MM-DD in the viewer's time zone, so "overdue" matches their calendar. */
export function today(timeZone?: string, now: Date = new Date()): string {
    const format: Intl.DateTimeFormatOptions = { year: 'numeric', month: '2-digit', day: '2-digit' };
    let formatter: Intl.DateTimeFormat;

    try {
        formatter = new Intl.DateTimeFormat('en-US', { ...format, timeZone });
    } catch {
        formatter = new Intl.DateTimeFormat('en-US', format);
    }

    const parts = Object.fromEntries(formatter.formatToParts(now).map((part) => [part.type, part.value]));

    return `${parts.year}-${parts.month}-${parts.day}`;
}

export function formatDate(date: string, locale?: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);

    if (!match) {
        return date;
    }

    const value = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
    const format: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' };

    try {
        return new Intl.DateTimeFormat(locale, format).format(value);
    } catch {
        return new Intl.DateTimeFormat(undefined, format).format(value);
    }
}

/** A server timestamp as local date and time. Anything unparseable is returned as it came. */
export function formatDateTime(timestamp: string, locale?: string, timeZone?: string): string {
    const value = new Date(timestamp);

    if (Number.isNaN(value.getTime())) {
        return timestamp;
    }

    const format: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'short' };

    try {
        return new Intl.DateTimeFormat(locale, { ...format, timeZone }).format(value);
    } catch {
        return new Intl.DateTimeFormat(undefined, format).format(value);
    }
}

export function isOverdue(task: Task, currentDate: string): boolean {
    return task.due_date !== null && task.status !== 'completed' && task.due_date < currentDate;
}

export function taskStats(tasks: Task[], currentDate: string) {
    const count = (status: Status): number => tasks.filter((task) => task.status === status).length;

    return {
        total: tasks.length,
        completed: count('completed'),
        inProgress: count('in_progress'),
        pending: count('todo'),
        overdue: tasks.filter((task) => isOverdue(task, currentDate)).length,
    };
}

export function plural(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

/** Quote workspace text before sharing it with the model, so it reads as data. */
export function quoted(text: string): string {
    const characters = [...text];

    return JSON.stringify(characters.length > 80 ? `${characters.slice(0, 79).join('')}…` : text);
}
