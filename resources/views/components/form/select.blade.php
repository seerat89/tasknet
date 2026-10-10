@props(['name', 'label', 'options', 'selected' => null])

<div class="flex flex-col gap-2">
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'w-full rounded-lg border bg-white px-3 py-2.5 text-base text-stone-900 shadow-xs outline-none focus-visible:border-teal-600 focus-visible:ring-2 focus-visible:ring-teal-600/25',
            'border-stone-300' => ! $errors->has($name),
            'border-red-500' => $errors->has($name),
        ]) }}
    >
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $selected) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p id="{{ $name }}-error" class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
