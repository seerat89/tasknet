@props(['project' => null, 'action', 'method' => 'POST', 'submit', 'cancel'])

<form method="POST" action="{{ $action }}" class="flex max-w-xl flex-col gap-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-auth.input name="name" label="Name" :value="$project?->name" maxlength="120" required autofocus />
    <x-form.textarea name="description" label="Description (optional)" :value="$project?->description" maxlength="2000" />

    <div class="flex flex-wrap gap-3">
        <x-auth.button class="w-auto!" :data-loading-text="str_starts_with($submit, 'Create') ? 'Creating…' : 'Saving…'">{{ $submit }}</x-auth.button>
        <x-link-button variant="secondary" :href="$cancel">Cancel</x-link-button>
    </div>
</form>
