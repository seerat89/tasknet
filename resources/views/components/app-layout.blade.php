@props(['title', 'description' => null, 'realtime' => false])

@php
    $user = auth()->user();

    if ($user) {
        $urgency = $user->demoExpiryUrgency();
        $expiryClasses = [
            'normal' => 'text-teal-700',
            'warning' => 'text-amber-700',
            'critical' => 'text-red-700',
            'today' => 'font-semibold text-red-700',
        ][$urgency];

        $days = $user->demoDaysRemaining();
        $expiryShort = $urgency === 'today' ? 'Expires today' : $days.' '.Str::plural('day', $days).' left';
    }

    $navigation = [
        ...($user ? [['label' => 'Projects', 'href' => route('projects.index'), 'active' => request()->routeIs('projects.*')]] : []),
        ['label' => 'About', 'href' => route('about'), 'active' => request()->routeIs('about')],
        ...($user ? [['label' => 'Settings', 'href' => route('settings'), 'active' => request()->routeIs('settings')]] : []),
    ];

    $focus = 'focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600';
    $logout = 'font-medium text-stone-600 hover:text-teal-700 '.$focus;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if ($realtime && $user)
            <meta name="workspace-channel" content="workspace.{{ auth()->id() }}">
            @if (config('broadcasting.default') === 'reverb')
                @php($scheme = config('tasknet.realtime.scheme') ?: request()->getScheme())
                <meta name="realtime-connection" content="{{ json_encode([
                    'key' => config('reverb.apps.apps.0.key'),
                    'host' => config('tasknet.realtime.host') ?: request()->getHost(),
                    'port' => (int) (config('tasknet.realtime.port') ?: ($scheme === 'https' ? 443 : 80)),
                    'scheme' => $scheme,
                ]) }}">
            @endif
        @endif
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        <title>{{ $title }} · TaskNet</title>
        <x-favicons />
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-web-analytics />
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
        <a href="#main-content" class="sr-only rounded-lg bg-white px-4 py-3 text-teal-700 focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-10 focus:outline-2 focus:outline-offset-4 focus:outline-teal-600">Skip to content</a>

        <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-5 sm:px-8">
            <header class="relative border-b border-stone-200/70">
                <div class="flex min-h-[4.5rem] items-center justify-between gap-4 py-3">
                    <div class="flex min-w-0 items-center gap-5">
                        <a href="{{ $user ? route('dashboard') : route('home') }}" aria-label="TaskNet home" class="shrink-0 rounded-sm {{ $focus }}">
                            <img src="{{ asset('brand/tasknet-logo-color.svg') }}" alt="TaskNet" width="1500" height="420" class="h-auto w-32 sm:w-40">
                        </a>

                        @if ($user)
                            <p class="hidden items-center gap-2 rounded-full border border-stone-200 bg-white/60 px-3 py-1 text-xs text-stone-500 lg:flex" title="{{ $user->demoExpiryLabel() }}">
                                <span class="font-semibold text-stone-700">{{ $user->demo_id }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="{{ $expiryClasses }}">{{ $expiryShort }}</span>
                            </p>
                        @endif
                    </div>

                    <nav aria-label="Main" class="hidden items-center gap-8 text-sm lg:flex">
                        @foreach ($navigation as $item)
                            <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif @class(['rounded-sm font-medium hover:text-teal-700', $focus, 'text-teal-700' => $item['active'], 'text-stone-600' => ! $item['active']])>{{ $item['label'] }}</a>
                        @endforeach
                        @if ($user)
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" data-loading-text="Logging out…" class="rounded-sm {{ $logout }}">Log out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="rounded-sm {{ $logout }}">Sign in</a>
                            <a href="{{ route('demo.create') }}" class="inline-flex items-center justify-center rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white transition-colors hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Start demo</a>
                        @endif
                    </nav>

                    <button type="button" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu" class="-mr-2 inline-flex size-11 items-center justify-center rounded-lg text-stone-700 hover:bg-stone-100 lg:hidden {{ $focus }}">
                        <span class="sr-only">Menu</span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" class="size-6">
                            <path data-menu-icon="open" d="M4 7h16M4 12h16M4 17h16" />
                            <path data-menu-icon="close" class="hidden" d="M6 6l12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <div id="mobile-menu" hidden class="border-t border-stone-200/70 pb-3 lg:hidden">
                    <nav aria-label="Mobile" class="flex flex-col py-2 text-base">
                        @foreach ($navigation as $item)
                            <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif @class(['flex min-h-12 items-center rounded-lg px-2 font-medium', $focus, 'text-teal-700' => $item['active'], 'text-stone-700' => ! $item['active']])>{{ $item['label'] }}</a>
                        @endforeach
                    </nav>

                    @guest
                        <div class="flex flex-col gap-3 border-t border-stone-200/70 px-2 pt-4">
                            <a href="{{ route('login') }}" class="flex min-h-12 items-center rounded-lg text-base {{ $logout }}">Sign in</a>
                            <a href="{{ route('demo.create') }}" class="flex min-h-12 w-full items-center justify-center rounded-lg bg-teal-700 px-4 text-base font-semibold text-white transition-colors hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Start demo</a>
                        </div>
                    @endguest

                    @if ($user)
                        <div class="flex flex-col gap-3 border-t border-stone-200/70 px-2 pt-4 text-sm">
                            <p class="flex flex-col gap-0.5">
                                <span class="font-semibold text-stone-700">{{ $user->demo_id }}</span>
                                <span class="{{ $expiryClasses }}">{{ $expiryShort }}</span>
                            </p>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" data-loading-text="Logging out…" class="flex min-h-12 w-full items-center rounded-lg text-base {{ $logout }}">Log out</button>
                            </form>
                        </div>
                    @endif
                </div>
            </header>

            <main id="main-content" class="flex flex-1 flex-col gap-6 py-8 sm:py-10">
                @if (session('success'))
                    <div role="status" class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ session('success') }}</div>
                @endif

                {{ $slot }}
            </main>

            <footer class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-stone-200/70 py-6 text-sm text-stone-500">
                <p>TaskNet · Taskboard for AI Agents</p>
                <nav aria-label="Footer" class="flex items-center gap-6">
                    <a href="{{ route('privacy') }}" class="rounded-sm underline decoration-stone-300 underline-offset-4 hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">Privacy</a>
                    <a href="{{ route('terms') }}" class="rounded-sm underline decoration-stone-300 underline-offset-4 hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">Terms</a>
                </nav>
            </footer>
        </div>
        <x-loading-indicator />
    </body>
</html>
