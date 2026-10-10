<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * Show the authenticated user's projects and workspace usage.
     */
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $projects = $request->user()->projects()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Completed),
            ])
            ->latest('updated_at')
            ->get();

        return view('dashboard', [
            'projects' => $projects,
            'projectCount' => $projects->count(),
            'projectLimit' => config('tasknet.limits.projects'),
            'taskCount' => $projects->sum('tasks_count'),
            'taskLimit' => config('tasknet.limits.tasks'),
            'completedTaskCount' => $projects->sum('completed_tasks_count'),
        ]);
    }
}
