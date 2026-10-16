<?php

namespace App\Http\Controllers;

use App\Actions\CreateProject;
use App\Actions\DeleteProject;
use App\Actions\UpdateProject;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    /**
     * Projects per page in the directory.
     */
    private const PER_PAGE = 20;

    /**
     * Sort options for the directory, keyed by their query-string value.
     */
    private const SORTS = ['updated', 'name', 'tasks'];

    /**
     * List the user's projects with search, sorting and pagination.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $search = Str::of((string) $request->query('q'))->squish()->limit(100, '')->toString();
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'updated';

        $projects = $request->user()->projects()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Completed),
            ])
            ->when($search !== '', function ($query) use ($search) {
                // Escape LIKE wildcards; the backslash escape works on both SQLite and PostgreSQL.
                $pattern = '%'.addcslashes(Str::lower($search), '%_\\').'%';

                $query->where(fn ($match) => $match
                    ->whereRaw("LOWER(projects.name) LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("LOWER(projects.description) LIKE ? ESCAPE '\\'", [$pattern]));
            })
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'tasks', fn ($query) => $query->orderByDesc('tasks_count')->orderBy('name'))
            ->when($sort === 'updated', fn ($query) => $query->latest('updated_at'))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'search' => $search,
            'sort' => $sort,
            'projectCount' => $request->user()->projects()->count(),
            'projectLimit' => config('tasknet.limits.projects'),
        ]);
    }

    /**
     * Show the form for creating a project.
     */
    public function create(): View
    {
        Gate::authorize('create', Project::class);

        return view('projects.create');
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $project = $createProject->create($request->user(), $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    /**
     * Show a project and its tasks.
     */
    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);

        return view('projects.show', [
            'project' => $project,
            'tasks' => $project->tasks()->orderBy('id')->get(),
            'taskCount' => $request->user()->tasks()->count(),
            'taskLimit' => config('tasknet.limits.tasks'),
        ]);
    }

    /**
     * Show the form for editing a project.
     */
    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.edit', ['project' => $project]);
    }

    /**
     * Update a project.
     */
    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $updateProject): RedirectResponse
    {
        $updateProject->update($request->user(), $project, $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    /**
     * Delete a project and its tasks.
     */
    public function destroy(Request $request, Project $project, DeleteProject $deleteProject): RedirectResponse
    {
        $deleteProject->delete($request->user(), $project);

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }
}
