<x-app-layout title="Edit project">
    <x-breadcrumbs :items="[['Projects', route('dashboard')], [$project->name, route('projects.show', $project)], ['Edit', null]]" />

    <h1 class="text-2xl font-semibold tracking-tight">Edit project</h1>

    <x-projects.form :project="$project" :action="route('projects.update', $project)" method="PUT" submit="Save changes" :cancel="route('projects.show', $project)" />
</x-app-layout>
