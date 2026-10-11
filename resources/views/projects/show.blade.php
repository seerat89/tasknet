@php
    $statusTones = ['todo' => 'stone', 'in_progress' => 'amber', 'completed' => 'teal'];
    $priorityTones = ['low' => 'stone', 'medium' => 'stone', 'high' => 'red'];
    $taskLimitReached = $taskCount >= $taskLimit;
@endphp

<x-app-layout :title="$project->name" :realtime="true">
    <x-breadcrumbs :items="[['Projects', route('dashboard')], [$project->name, null]]" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-2">
            <h1 class="text-2xl font-semibold tracking-tight break-words">{{ $project->name }}</h1>
            @if ($project->description)
                <p class="max-w-2xl text-sm leading-6 whitespace-pre-line text-stone-600">{{ $project->description }}</p>
            @endif
        </div>

        <div class="flex flex-wrap gap-3">
            <x-link-button variant="secondary" :href="route('projects.edit', $project)">Edit project</x-link-button>
            <x-confirm-delete
                name="delete-project"
                heading="Delete this project?"
                :message="'“'.$project->name.'” and all '.$tasks->count().' of its '.Str::plural('task', $tasks->count()).' will be permanently deleted.'"
                :action="route('projects.destroy', $project)"
                label="Delete project"
            />
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-stone-200 pt-6">
        <div>
            <h2 class="text-lg font-semibold">Tasks</h2>
            <p class="text-sm text-stone-500">{{ $tasks->count() }} in this project · {{ $taskCount }} / {{ $taskLimit }} in your workspace</p>
        </div>

        @unless ($taskLimitReached)
            <x-link-button :href="route('projects.tasks.create', $project)">New task</x-link-button>
        @endunless
    </div>

    @if ($taskLimitReached)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">This demo workspace has reached its limit of {{ $taskLimit }} tasks. Delete a task to create another.</p>
    @endif

    @if ($tasks->isEmpty())
        <section class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-stone-300 bg-white p-8">
            <h3 class="text-base font-semibold">No tasks yet.</h3>
            <p class="max-w-md text-sm leading-6 text-stone-600">Add a task here, or let your AI agent create them once MCP is connected.</p>
            @unless ($taskLimitReached)
                <x-link-button :href="route('projects.tasks.create', $project)">New task</x-link-button>
            @endunless
        </section>
    @else
        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <div aria-hidden="true" class="hidden grid-cols-[1fr_7rem_6rem_7rem_3rem] gap-4 border-b border-stone-200 bg-stone-50 px-5 py-2.5 text-xs font-medium tracking-wide text-stone-500 uppercase md:grid">
                <span>Title</span><span>Status</span><span>Priority</span><span>Due</span><span></span>
            </div>
            <ul class="divide-y divide-stone-200">
                @foreach ($tasks as $task)
                    <li class="flex flex-col gap-2 px-5 py-4 md:grid md:grid-cols-[1fr_7rem_6rem_7rem_3rem] md:items-center md:gap-4">
                        <p class="font-medium break-words">{{ $task->title }}</p>
                        <p><x-badge :tone="$statusTones[$task->status->value]">{{ $task->status->label() }}</x-badge></p>
                        <p><x-badge :tone="$priorityTones[$task->priority->value]">{{ $task->priority->label() }}</x-badge></p>
                        <p class="text-sm text-stone-600">{{ $task->due_date ? $task->due_date->format('M j, Y') : 'No due date' }}</p>
                        <p><a href="{{ route('projects.tasks.edit', [$project, $task]) }}" aria-label="Edit task {{ $task->title }}" class="rounded-sm text-sm font-medium text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">Edit</a></p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-app-layout>
