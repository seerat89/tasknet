@props(['items'])

{{-- Items are [label, url, dynamic]. Dynamic labels (project and task names) truncate with an ellipsis; fixed labels never shrink. --}}
<nav aria-label="Breadcrumb" class="text-xs">
    <ol class="flex items-center gap-x-1.5 whitespace-nowrap text-stone-500">
        @foreach ($items as $item)
            @php([$label, $url] = $item)
            @php($dynamic = $item[2] ?? false)
            <li @class(['flex items-center gap-1.5', 'min-w-0' => $dynamic, 'shrink-0' => ! $dynamic])>
                @if ($url)
                    <a href="{{ $url }}" @if ($dynamic) title="{{ $label }}" @endif @class(['rounded-sm text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600', 'block max-w-[9rem] truncate sm:max-w-xs' => $dynamic])>{{ $label }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif @if ($dynamic) title="{{ $label }}" @endif @class(['block max-w-[9rem] truncate sm:max-w-xs' => $dynamic])>{{ $label }}</span>
                @endif
                @unless ($loop->last)
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3 shrink-0 text-stone-400"><path d="m6 3 5 5-5 5" /></svg>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
