<x-app-layout title="Edit task">
    <x-breadcrumbs :items="[['Projects', route('projects.index')], [$project->name, route('projects.show', $project), true], ['Edit task', null]]" />

    <h1 class="text-2xl font-semibold tracking-tight">Edit task</h1>

    <x-tasks.form :task="$task" :action="route('projects.tasks.update', [$project, $task])" method="PUT" submit="Save changes" :cancel="route('projects.show', $project)" />

    <div class="border-t border-stone-200 pt-6">
        <x-confirm-delete
            name="delete-task"
            heading="Delete this task?"
            :message="'“'.$task->title.'” will be permanently deleted.'"
            :action="route('projects.tasks.destroy', [$project, $task])"
            label="Delete task"
        />
    </div>
</x-app-layout>
