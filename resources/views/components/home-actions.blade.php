@props(['primaryFirst' => false])

<div class="flex flex-wrap items-center gap-4 sm:gap-6">
    @auth
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-auth.button>Log out</x-auth.button>
        </form>
    @else
        <a href="{{ route('login') }}" @class([
            'rounded-sm text-sm font-medium text-stone-600 hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600',
            'order-2' => $primaryFirst,
        ])>Log in</a>
        <form method="POST" action="{{ route('demo.store') }}" @class(['order-1' => $primaryFirst])>
            @csrf
            <button type="submit" @class([
                'inline-flex items-center justify-center rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600',
                'min-w-40 gap-3' => $primaryFirst,
            ])>
                Start demo
                @if ($primaryFirst)
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4">
                        <path d="M4 10h12m-5-5 5 5-5 5" />
                    </svg>
                @endif
            </button>
        </form>
    @endauth
</div>
