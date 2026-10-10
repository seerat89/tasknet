@props(['heading' => null])

<div {{ $attributes->class('rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200') }}>
    @if ($heading)
        <p class="font-semibold">{{ $heading }}</p>
    @endif
    <p>TaskNet is a public MCP testing environment. Do not use it for real projects, confidential information, personal data, or anything you need to keep. This workspace and its data are automatically deleted after {{ config('tasknet.demo.lifetime_days') }} days.</p>
</div>
