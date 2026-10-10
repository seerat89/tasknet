@props(['name', 'heading', 'message', 'action', 'label' => 'Delete'])

<button type="button" data-dialog-open="{{ $name }}" {{ $attributes->class('rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600') }}>{{ $label }}</button>

<dialog id="{{ $name }}" aria-labelledby="{{ $name }}-heading" aria-describedby="{{ $name }}-message" class="m-auto w-[calc(100%-2.5rem)] max-w-md rounded-2xl border border-stone-200 bg-white p-6 text-stone-900 shadow-lg backdrop:bg-stone-900/40">
    <form method="POST" action="{{ $action }}" class="flex flex-col gap-4">
        @csrf
        @method('DELETE')
        <h2 id="{{ $name }}-heading" class="text-lg font-semibold tracking-tight">{{ $heading }}</h2>
        <p id="{{ $name }}-message" class="text-sm leading-6 text-stone-600">{{ $message }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" data-dialog-close autofocus class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-900 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Cancel</button>
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">{{ $label }}</button>
        </div>
    </form>
</dialog>
