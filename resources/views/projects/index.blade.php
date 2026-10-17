<x-app-layout title="Projects">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-3xl font-semibold tracking-tight">Projects</h1>
            <p class="text-stone-600">{{ $projectCount }} of {{ $projectLimit }} projects in this workspace.</p>
        </div>

        @if ($projectCount < $projectLimit)
            <x-link-button :href="route('projects.create')">New project</x-link-button>
        @endif
    </div>

    @if ($projectCount > 0)
        <form method="GET" action="{{ route('projects.index') }}" role="search" data-project-filter class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <label for="q" class="text-sm font-medium text-stone-700">Search projects</label>
                <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Name or description" class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
            </div>
            <div class="flex flex-col gap-1">
                <label for="sort" class="text-sm font-medium text-stone-700">Sort by</label>
                <div class="relative">
                    <select id="sort" name="sort" class="w-full appearance-none rounded-lg border border-stone-300 bg-white py-2.5 pr-10 pl-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                        <option value="updated" @selected($sort === 'updated')>Recently updated</option>
                        <option value="name" @selected($sort === 'name')>Name</option>
                        <option value="tasks" @selected($sort === 'tasks')>Most tasks</option>
                    </select>
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute inset-y-0 right-3 my-auto size-4 text-stone-500"><path d="m4 6 4 4 4-4" /></svg>
                </div>
            </div>
            <noscript><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Search</button></noscript>
        </form>
    @endif

    <div data-project-results class="flex flex-col gap-6">
        @if ($projectCount === 0)
            <section class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-stone-300 bg-white p-8">
                <h2 class="text-lg font-semibold">No projects yet.</h2>
                <p class="max-w-md text-sm leading-6 text-stone-600">Create a project by hand now, and manage it through your AI agent once MCP is connected.</p>
                <x-link-button :href="route('projects.create')">New project</x-link-button>
            </section>
        @elseif ($projects->isEmpty())
            <section class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-stone-300 bg-white p-8">
                <h2 class="text-lg font-semibold">No projects matched “{{ $search }}”.</h2>
                <p class="max-w-md text-sm leading-6 text-stone-600">Try a different name or part of a description.</p>
                <x-link-button :href="route('projects.index', ['sort' => $sort])" variant="secondary">Clear search</x-link-button>
            </section>
        @else
            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white">
                <table class="block w-full text-left text-sm sm:table">
                    <caption class="sr-only">Projects</caption>
                    <thead class="sr-only sm:not-sr-only">
                        <tr class="border-b border-stone-200 text-xs text-stone-500">
                            <th scope="col" class="px-5 py-3 font-medium">Project</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Tasks</th>
                            <th scope="col" class="px-3 py-3 text-right font-medium">Completed</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="block divide-y divide-stone-200 sm:table-row-group">
                        @foreach ($projects as $project)
                            <tr class="block px-5 py-4 sm:table-row sm:p-0">
                                <td class="block sm:table-cell sm:w-full sm:max-w-0 sm:px-5 sm:py-4">
                                    <a href="{{ route('projects.show', $project) }}" title="{{ $project->name }}" class="block truncate rounded-sm font-semibold text-stone-900 hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">{{ $project->name }}</a>
                                    @if ($project->description)
                                        <p class="mt-0.5 truncate text-stone-500" title="{{ $project->description }}">{{ $project->description }}</p>
                                    @endif
                                </td>
                                <td class="mt-2 mr-4 inline-block text-stone-600 sm:mt-0 sm:mr-0 sm:table-cell sm:px-3 sm:py-4 sm:text-right"><span class="text-stone-500 sm:hidden">Tasks </span>{{ $project->tasks_count }}</td>
                                <td class="mt-2 mr-4 inline-block text-stone-600 sm:mt-0 sm:mr-0 sm:table-cell sm:px-3 sm:py-4 sm:text-right"><span class="text-stone-500 sm:hidden">Completed </span>{{ $project->completed_tasks_count }}</td>
                                <td class="mt-2 inline-block text-stone-600 sm:mt-0 sm:table-cell sm:px-5 sm:py-4 sm:text-right sm:whitespace-nowrap"><span class="text-stone-500 sm:hidden">Updated </span>{{ $project->updated_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($projects->hasPages())
                <nav aria-label="Pagination" class="flex items-center justify-between gap-4 text-sm">
                    @if ($projects->onFirstPage())
                        <span class="text-stone-400">Previous</span>
                    @else
                        <a href="{{ $projects->previousPageUrl() }}" rel="prev" class="rounded-sm font-medium text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">Previous</a>
                    @endif

                    <span class="text-stone-500">Page {{ $projects->currentPage() }} of {{ $projects->lastPage() }}</span>

                    @if ($projects->hasMorePages())
                        <a href="{{ $projects->nextPageUrl() }}" rel="next" class="rounded-sm font-medium text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">Next</a>
                    @else
                        <span class="text-stone-400">Next</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-app-layout>
