@props(['name', 'label', 'type' => 'text', 'value' => null])

<div class="flex flex-col gap-2">
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'w-full rounded-lg border bg-white px-3 py-2.5 text-base text-stone-900 shadow-xs outline-none focus-visible:border-teal-600 focus-visible:ring-2 focus-visible:ring-teal-600/25 dark:bg-stone-950 dark:text-stone-100',
            'border-stone-300 dark:border-stone-700' => ! $errors->has($name),
            'border-red-500 dark:border-red-400' => $errors->has($name),
        ]) }}
    >
    @error($name)
        <p id="{{ $name }}-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
