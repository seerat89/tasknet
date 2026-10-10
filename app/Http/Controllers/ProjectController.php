<?php

namespace App\Http\Controllers;

use App\Actions\CreateProject;
use App\Actions\DeleteProject;
use App\Actions\UpdateProject;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
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

        return redirect()->route('dashboard')->with('success', 'Project deleted.');
    }
}
