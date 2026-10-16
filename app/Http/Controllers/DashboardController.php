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
     * Number of projects listed in the recent-projects section.
     */
    private const RECENT_PROJECTS = 4;

    /**
     * Show an overview of the authenticated user's workspace.
     */
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $user = $request->user();

        $recentProjects = $user->projects()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Completed),
            ])
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::RECENT_PROJECTS)
            ->get();

        // Every project, busiest first; the chart shows the first five and its search covers all of them.
        $chartProjects = $user->projects()
            ->select(['projects.id', 'projects.name', 'projects.description'])
            ->withCount('tasks')
            ->orderByDesc('tasks_count')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $tasksByStatus = $user->tasks()->toBase()
            ->selectRaw('tasks.status, count(*) as total')
            ->groupBy('tasks.status')
            ->pluck('total', 'status');

        $statusCounts = [
            'todo' => (int) $tasksByStatus->get(TaskStatus::Todo->value, 0),
            'in_progress' => (int) $tasksByStatus->get(TaskStatus::InProgress->value, 0),
            'completed' => (int) $tasksByStatus->get(TaskStatus::Completed->value, 0),
        ];

        return view('dashboard', [
            'recentProjects' => $recentProjects,
            'chartProjects' => $chartProjects,
            'projectCount' => $user->projects()->count(),
            'projectLimit' => config('tasknet.limits.projects'),
            'taskCount' => array_sum($statusCounts),
            'taskLimit' => config('tasknet.limits.tasks'),
            'completedTaskCount' => $statusCounts['completed'],
            'statusCounts' => $statusCounts,
        ]);
    }
}
