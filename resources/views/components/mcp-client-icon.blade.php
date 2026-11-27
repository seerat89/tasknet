{{--
    A decorative mark for a client: initials for a service recognised by where it returns after sign-in,
    otherwise a generic plug. No vendor logos are used. The client's name is always shown beside it.
--}}
@props(['provider' => null])

<span aria-hidden="true" data-client-icon="{{ $provider?->value ?? 'generic' }}" @class([
    'flex size-10 shrink-0 items-center justify-center rounded-lg border text-sm font-bold',
    'border-teal-200 bg-teal-50 text-teal-800' => $provider === \App\Enums\McpClientProvider::ChatGpt,
    'border-amber-200 bg-amber-50 text-amber-900' => $provider === \App\Enums\McpClientProvider::Claude,
    'border-stone-200 bg-stone-100 text-stone-600' => $provider === null,
])>
    @if ($provider === \App\Enums\McpClientProvider::ChatGpt)
        GPT
    @elseif ($provider === \App\Enums\McpClientProvider::Claude)
        Cl
    @else
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-5">
            <path d="M9 3v5m6-5v5M6 8h12v3a6 6 0 0 1-12 0V8Zm6 9v4" />
        </svg>
    @endif
</span>
