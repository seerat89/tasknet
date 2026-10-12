@props(['heading' => null])

<div {{ $attributes->class('rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200') }}>
    @if ($heading)
        <p class="font-semibold">{{ $heading }}</p>
    @endif
    <p>TaskNet is a demonstration service. Do not use it for sensitive, confidential or important information. A workspace and its data are deleted automatically {{ config('tasknet.demo.lifetime_days') }} days after it is created.</p>
</div>
