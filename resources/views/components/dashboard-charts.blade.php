@props(['statusCounts', 'projects', 'projectCount'])

@php
    $total = array_sum($statusCounts);
    $segments = [
        ['label' => 'Todo', 'count' => $statusCounts['todo'], 'stroke' => 'stroke-slate-400', 'dot' => 'bg-slate-400'],
        ['label' => 'In progress', 'count' => $statusCounts['in_progress'], 'stroke' => 'stroke-[#ca8a2e]', 'dot' => 'bg-[#ca8a2e]'],
        ['label' => 'Completed', 'count' => $statusCounts['completed'], 'stroke' => 'stroke-teal-600', 'dot' => 'bg-teal-600'],
    ];
    $visible = collect($segments)->where('count', '>', 0)->count();
    $strokeWidth = 14;
    $circumference = 2 * M_PI * 40;
    // Segments run edge to edge; with rounded ends each later segment overlaps the end of the one before it.
    $offset = 0;
    $summary = collect($segments)->map(fn ($segment) => $segment['count'].' '.strtolower($segment['label']))->implode(', ');

    // $projects holds every project, busiest first; the card shows five and the search covers all of them.
    $visibleProjects = $projects->take(5);
    $maxTasks = max(1, (int) $visibleProjects->max('tasks_count'));
    $searchData = $projects->map(fn ($project) => [
        'name' => $project->name,
        'description' => (string) $project->description,
        'tasks' => (int) $project->tasks_count,
    ])->values();
@endphp

<div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
    <section aria-labelledby="status-chart-heading" class="flex flex-col gap-6 rounded-2xl border border-stone-200 bg-white p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="status-chart-heading" class="text-base font-semibold">Task status</h2>
                <p class="text-sm text-stone-500">Current distribution across the workspace.</p>
            </div>
            <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-teal-700">
                <span aria-hidden="true" class="flex size-4 items-center justify-center rounded-full bg-teal-100"><span class="size-2 rounded-full bg-teal-600"></span></span>
                Realtime
            </span>
        </div>

        <div class="relative mx-auto size-44 shrink-0">
            <svg viewBox="0 0 100 100" role="img" aria-label="Task status: {{ $summary }}" class="size-full -rotate-90">
                <circle cx="50" cy="50" r="40" fill="none" stroke-width="{{ $strokeWidth }}" class="stroke-stone-100" />
                @foreach ($segments as $segment)
                    @if ($segment['count'] > 0)
                        @php($length = $segment['count'] / $total * $circumference)
                        <circle cx="50" cy="50" r="40" fill="none" stroke-width="{{ $strokeWidth }}" stroke-linecap="{{ $visible > 1 ? 'round' : 'butt' }}" stroke-dasharray="{{ round($length, 2) }} {{ round($circumference - $length, 2) }}" stroke-dashoffset="{{ round(-$offset, 2) }}" class="{{ $segment['stroke'] }}" />
                        @php($offset += $length)
                    @endif
                @endforeach
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-semibold">{{ $total }}</span>
                <span class="text-xs text-stone-500">{{ Str::plural('task', $total) }}</span>
            </div>
        </div>

        <ul class="grid grid-cols-3 gap-3">
            @foreach ($segments as $segment)
                <li class="flex flex-col gap-1 rounded-xl bg-stone-50 px-3 py-2.5">
                    <span class="flex items-center gap-2 text-sm text-stone-600">
                        <span aria-hidden="true" class="size-2.5 shrink-0 rounded-full {{ $segment['dot'] }}"></span>
                        {{ $segment['label'] }}
                    </span>
                    <span class="text-xl font-semibold">{{ $segment['count'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section aria-labelledby="project-chart-heading" data-project-chart class="flex flex-col gap-5 rounded-2xl border border-stone-200 bg-white p-6">
        <div>
            <h2 id="project-chart-heading" class="text-base font-semibold">Tasks by project</h2>
            <p class="text-sm text-stone-500">{{ $projectCount > 5 ? 'Task volume across your busiest projects.' : 'Task volume across your current projects.' }}</p>
        </div>

        <div class="relative">
            <label for="project-chart-search" class="sr-only">Search projects</label>
            <input id="project-chart-search" type="search" data-project-search autocomplete="off" placeholder="Search projects..." class="w-full rounded-lg border border-stone-300 bg-white py-2 pr-10 pl-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 [&::-webkit-search-cancel-button]:hidden">
            <button type="button" data-project-search-clear hidden aria-label="Clear search" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-lg text-stone-500 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" class="size-4"><path d="m4 4 8 8M12 4l-8 8" /></svg>
            </button>
        </div>

        <ul data-project-rows class="flex flex-col gap-4 text-sm">
            @foreach ($visibleProjects as $project)
                <li class="grid grid-cols-[minmax(0,9rem)_1fr_1.5rem] items-center gap-4 sm:grid-cols-[minmax(0,11rem)_1fr_1.5rem]">
                    <span class="truncate font-semibold" title="{{ $project->name }}">{{ $project->name }}</span>
                    <span class="h-2.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                        <span class="block h-full rounded-full bg-teal-600" style="width: {{ round($project->tasks_count / $maxTasks * 100) }}%"></span>
                    </span>
                    <span class="text-right text-stone-500">{{ $project->tasks_count }}</span>
                </li>
            @endforeach
        </ul>

        <p data-project-empty hidden role="status" class="text-sm text-stone-500">No matching projects</p>

        <template data-project-row>
            <li class="grid grid-cols-[minmax(0,9rem)_1fr_1.5rem] items-center gap-4 sm:grid-cols-[minmax(0,11rem)_1fr_1.5rem]">
                <span class="truncate font-semibold" data-name></span>
                <span class="h-2.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                    <span class="block h-full rounded-full bg-teal-600" data-bar></span>
                </span>
                <span class="text-right text-stone-500" data-count></span>
            </li>
        </template>

        <script type="application/json" data-project-data>@json($searchData)</script>
    </section>
</div>
