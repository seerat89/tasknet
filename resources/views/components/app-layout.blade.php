@props(['title', 'realtime' => false])

@php
    $expiryClasses = [
        'normal' => 'text-teal-700',
        'warning' => 'text-amber-700',
        'critical' => 'text-red-700',
        'today' => 'font-semibold text-red-700',
    ][auth()->user()->demoExpiryUrgency()];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if ($realtime)
            <meta name="workspace-channel" content="workspace.{{ auth()->id() }}">
        @endif
        <title>{{ $title }} · TaskNet</title>
        <link rel="icon" href="{{ asset('brand/tasknet-mark.svg') }}" type="image/svg+xml">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
        <a href="#main-content" class="sr-only rounded-lg bg-white px-4 py-3 text-teal-700 focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-10 focus:outline-2 focus:outline-offset-4 focus:outline-teal-600">Skip to content</a>

        <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-5 sm:px-8">
            <header class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-b border-stone-200/70 py-5">
                <a href="{{ route('dashboard') }}" aria-label="TaskNet dashboard" class="shrink-0 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">
                    <img src="{{ asset('brand/tasknet-logo-color.svg') }}" alt="TaskNet" width="1500" height="420" class="h-auto w-32 sm:w-40">
                </a>

                <div class="flex items-center gap-4 text-sm text-stone-500">
                    <p>
                        <span class="font-medium text-stone-700">{{ auth()->user()->demo_id }}</span>
                        <span aria-hidden="true">·</span>
                        <span class="{{ $expiryClasses }}">{{ auth()->user()->demoExpiryLabel() }}</span>
                    </p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-4 hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">Log out</button>
                    </form>
                </div>
            </header>

            <main id="main-content" class="flex flex-1 flex-col gap-6 py-8 sm:py-10">
                @if (session('success'))
                    <div role="status" class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ session('success') }}</div>
                @endif

                {{ $slot }}
            </main>

            <footer class="border-t border-stone-200/60 py-5 text-xs text-stone-500">
                TaskNet · Temporary demo workspace · Do not store real or sensitive data
            </footer>
        </div>
    </body>
</html>
