<x-app-layout title="Projects" :realtime="true">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-semibold tracking-tight">Projects</h1>
            <p class="max-w-lg text-sm leading-6 text-stone-600">Organize a few projects and tasks, then connect your AI agent through MCP.</p>
        </div>

        @if ($projectCount < $projectLimit)
            <x-link-button :href="route('projects.create')">New project</x-link-button>
        @endif
    </div>

    <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
        <div class="flex gap-2">
            <dt class="text-stone-500">Projects</dt>
            <dd class="font-medium">{{ $projectCount }} / {{ $projectLimit }}</dd>
        </div>
        <div class="flex gap-2">
            <dt class="text-stone-500">Tasks</dt>
            <dd class="font-medium">{{ $taskCount }} / {{ $taskLimit }}</dd>
        </div>
        <div class="flex gap-2">
            <dt class="text-stone-500">Completed</dt>
            <dd class="font-medium">{{ $completedTaskCount }}</dd>
        </div>
    </dl>

    @if ($projectCount >= $projectLimit)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">This demo workspace has reached its limit of {{ $projectLimit }} projects. Delete a project to create another.</p>
    @endif

    @if ($projects->isEmpty())
        <section class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-stone-300 bg-white p-8">
            <h2 class="text-lg font-semibold">No projects yet.</h2>
            <p class="max-w-md text-sm leading-6 text-stone-600">Create a project by hand now, and manage it through your AI agent once MCP is connected.</p>
            <x-link-button :href="route('projects.create')">New project</x-link-button>
        </section>
    @else
        <ul class="grid gap-4 sm:grid-cols-2">
            @foreach ($projects as $project)
                <li>
                    <a href="{{ route('projects.show', $project) }}" class="flex h-full flex-col gap-3 rounded-xl border border-stone-200 bg-white p-5 transition-colors hover:border-teal-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                        <h2 class="text-base font-semibold">{{ $project->name }}</h2>
                        @if ($project->description)
                            <p class="line-clamp-2 text-sm leading-6 text-stone-600">{{ $project->description }}</p>
                        @endif
                        <p class="mt-auto text-xs text-stone-500">
                            {{ $project->tasks_count }} {{ Str::plural('task', $project->tasks_count) }}
                            · {{ $project->completed_tasks_count }} completed
                            · Updated {{ $project->updated_at->format('M j, Y') }}
                        </p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-app-layout>
