@props(['variant' => 'primary'])

<a {{ $attributes->class([
    'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600',
    'bg-teal-700 text-white hover:bg-teal-800' => $variant === 'primary',
    'border border-stone-300 bg-white text-stone-900 hover:bg-stone-100' => $variant === 'secondary',
]) }}>{{ $slot }}</a>
