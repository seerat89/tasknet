<x-app-layout title="New project">
    <x-breadcrumbs :items="[['Projects', route('projects.index')], ['New project', null]]" />

    <h1 class="text-2xl font-semibold tracking-tight">New project</h1>

    <x-projects.form :action="route('projects.store')" submit="Create project" :cancel="route('projects.index')" />
</x-app-layout>
