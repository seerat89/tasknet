@props(['task' => null, 'action', 'method' => 'POST', 'submit', 'cancel'])

<form method="POST" action="{{ $action }}" class="flex max-w-xl flex-col gap-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-auth.input name="title" label="Title" :value="$task?->title" maxlength="160" required autofocus />
    <x-form.textarea name="description" label="Description (optional)" :value="$task?->description" maxlength="5000" />

    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.select name="status" label="Status" :options="collect(App\Enums\TaskStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()" :selected="($task?->status ?? App\Enums\TaskStatus::Todo)->value" required />
        <x-form.select name="priority" label="Priority" :options="collect(App\Enums\TaskPriority::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()" :selected="($task?->priority ?? App\Enums\TaskPriority::Medium)->value" required />
    </div>

    <x-auth.input name="due_date" type="date" label="Due date (optional)" :value="$task?->due_date?->format('Y-m-d')" class="sm:max-w-xs" />

    <div class="flex flex-wrap gap-3">
        <x-auth.button class="w-auto!" :data-loading-text="str_starts_with($submit, 'Create') ? 'Creating…' : 'Saving…'">{{ $submit }}</x-auth.button>
        <x-link-button variant="secondary" :href="$cancel">Cancel</x-link-button>
    </div>
</form>
