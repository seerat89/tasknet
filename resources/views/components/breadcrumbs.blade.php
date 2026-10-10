@props(['items'])

<nav aria-label="Breadcrumb" class="text-xs">
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-stone-500">
        @foreach ($items as [$label, $url])
            <li class="flex min-w-0 items-center gap-1.5">
                @if ($url)
                    <a href="{{ $url }}" class="rounded-sm text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">{{ $label }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif class="break-words">{{ $label }}</span>
                @endif
                @unless ($loop->last)
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3 shrink-0 text-stone-400"><path d="m6 3 5 5-5 5" /></svg>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
