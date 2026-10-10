@props(['title', 'description'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · TaskNet</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
        <main class="flex min-h-screen flex-col items-center justify-center gap-8 px-4 py-12 sm:px-6">
            <header class="flex flex-col items-center gap-2 text-center">
                <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg text-2xl font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-teal-700 text-white" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12 4 4L19 6" />
                            <path d="M19 13v6H5V5h8" />
                        </svg>
                    </span>
                    TaskNet
                </a>
                <p class="text-sm text-stone-500 dark:text-stone-400">Taskboard for AI Agents</p>
            </header>

            <section aria-labelledby="auth-title" class="flex w-full max-w-md flex-col gap-6 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8 dark:border-stone-800 dark:bg-stone-900">
                <div class="flex flex-col gap-2">
                    <h1 id="auth-title" class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
                    <p class="text-sm leading-6 text-stone-600 dark:text-stone-400">{{ $description }}</p>
                </div>

                @if (session('status'))
                    <div role="status" class="rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm leading-6 text-teal-900 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-200">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        Please check the highlighted fields and try again.
                    </div>
                @endif

                {{ $slot }}
            </section>

            <p class="text-center text-xs text-stone-500 dark:text-stone-400">Your projects. Your tasks. Your agents.</p>
        </main>
    </body>
</html>
