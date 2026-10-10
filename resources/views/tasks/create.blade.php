<x-app-layout title="New task">
    <x-breadcrumbs :items="[['Projects', route('dashboard')], [$project->name, route('projects.show', $project)], ['New task', null]]" />

    <h1 class="text-2xl font-semibold tracking-tight">New task</h1>

    <x-tasks.form :action="route('projects.tasks.store', $project)" submit="Create task" :cancel="route('projects.show', $project)" />
</x-app-layout>
