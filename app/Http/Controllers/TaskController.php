<?php

namespace App\Http\Controllers;

use App\Actions\CreateTask;
use App\Actions\DeleteTask;
use App\Actions\UpdateTask;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * Show the form for creating a task in a project.
     */
    public function create(Project $project): View
    {
        Gate::authorize('create', [Task::class, $project]);

        return view('tasks.create', ['project' => $project]);
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request, Project $project, CreateTask $createTask): RedirectResponse
    {
        $createTask->create($request->user(), $project, $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Task created.');
    }

    /**
     * Show the form for editing a task.
     */
    public function edit(Project $project, Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.edit', ['project' => $project, 'task' => $task]);
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task, UpdateTask $updateTask): RedirectResponse
    {
        $updateTask->update($request->user(), $project, $task, $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Task updated.');
    }

    /**
     * Delete a task.
     */
    public function destroy(Request $request, Project $project, Task $task, DeleteTask $deleteTask): RedirectResponse
    {
        $deleteTask->delete($request->user(), $project, $task);

        return redirect()->route('projects.show', $project)->with('success', 'Task deleted.');
    }
}
