@props(['tone' => 'stone'])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-stone-100 text-stone-700' => $tone === 'stone',
    'bg-teal-50 text-teal-800' => $tone === 'teal',
    'bg-amber-50 text-amber-800' => $tone === 'amber',
    'bg-red-50 text-red-800' => $tone === 'red',
]) }}>{{ $slot }}</span>
