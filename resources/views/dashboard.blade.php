<x-app-layout title="Workspace overview" :realtime="true">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-3xl font-semibold tracking-tight">Workspace overview</h1>
            <p class="max-w-lg text-stone-600">A quick look at your projects and tasks.</p>
        </div>

        @if ($projectCount < $projectLimit)
            <x-link-button :href="route('projects.create')">New project</x-link-button>
        @endif
    </div>

    @php
        $completedShare = $taskCount > 0 ? round($completedTaskCount / $taskCount * 100, 1) : 0;
        $stats = [
            ['label' => 'Projects', 'value' => $projectCount.' / '.$projectLimit, 'note' => max(0, $projectLimit - $projectCount).' remaining'],
            ['label' => 'Tasks', 'value' => $taskCount.' / '.$taskLimit, 'note' => max(0, $taskLimit - $taskCount).' remaining'],
            ['label' => 'Completed', 'value' => $completedTaskCount, 'note' => $completedShare.'% of tasks'],
            ['label' => 'In progress', 'value' => $statusCounts['in_progress'], 'note' => 'currently active'],
        ];
    @endphp

    <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-2xl border border-stone-200 bg-white p-5">
                <dt class="text-sm text-stone-600">{{ $stat['label'] }}</dt>
                <dd class="text-3xl font-bold tracking-tight">{{ $stat['value'] }}</dd>
                <dd class="text-xs text-stone-500">{{ $stat['note'] }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($taskCount > 0)
        <x-dashboard-charts :status-counts="$statusCounts" :projects="$chartProjects" :project-count="$projectCount" />
    @endif

    @if ($projectCount >= $projectLimit)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">This demo workspace has reached its limit of {{ $projectLimit }} projects. Delete a project to create another.</p>
    @endif

    @if ($projectCount === 0)
        <section class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-stone-300 bg-white p-8">
            <h2 class="text-lg font-semibold">No projects yet.</h2>
            <p class="max-w-md text-sm leading-6 text-stone-600">Create a project by hand now, and manage it through your AI agent once MCP is connected.</p>
            <x-link-button :href="route('projects.create')">New project</x-link-button>
        </section>
    @else
        <div class="-mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <h2 class="text-xl font-semibold tracking-tight">Recent projects <span class="ml-1 text-sm font-normal text-stone-500">{{ $recentProjects->count() }} of {{ $projectCount }}</span></h2>
            <a href="{{ route('projects.index') }}" class="rounded-sm text-sm font-medium text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">View all projects</a>
        </div>

        <ul class="grid gap-4 sm:grid-cols-2">
            @foreach ($recentProjects as $project)
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
