<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="TaskNet helps you organize your work with projects and tasks — simple, clean and focused.">
        <meta name="color-scheme" content="light">
        <title>TaskNet · Simple projects. Clear tasks.</title>
        <link rel="icon" href="{{ asset('brand/tasknet-mark.svg') }}" type="image/svg+xml">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
        <a href="#main-content" class="sr-only rounded-lg bg-white px-4 py-3 text-teal-700 focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-10 focus:outline-2 focus:outline-offset-4 focus:outline-teal-600">Skip to content</a>

        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-5 sm:px-8 lg:px-10">
            <header class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200/60 py-6 sm:py-7">
                <a href="{{ route('home') }}" aria-label="TaskNet home" class="shrink-0 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">
                    <img src="{{ asset('brand/tasknet-logo-color.svg') }}" alt="TaskNet" width="1500" height="420" class="h-auto w-38 sm:w-48">
                </a>
                <nav aria-label="Account">
                    <x-home-actions />
                </nav>
            </header>

            <main id="main-content" class="flex flex-1 items-center pt-10 pb-20 sm:pt-12 sm:pb-24 lg:pb-32">
                <section aria-labelledby="hero-heading" class="grid w-full items-center gap-12 lg:grid-cols-[1fr_1.15fr] lg:gap-12">
                    <div class="flex flex-col items-start gap-8">
                        <div class="flex flex-col gap-6">
                            <h1 id="hero-heading" class="text-[clamp(2.125rem,9.5vw,3.25rem)] leading-[1.06] font-bold tracking-tight sm:text-6xl lg:text-[3.5rem] xl:text-[4.25rem]">
                                <span class="block whitespace-nowrap">Simple projects.</span>
                                <span class="block whitespace-nowrap text-teal-700">Clear tasks.</span>
                            </h1>
                            <p class="max-w-md text-lg leading-8 text-stone-600">TaskNet helps you organize your work with projects and tasks — simple, clean and focused.</p>
                        </div>

                        <div class="flex flex-col gap-4">
                            @guest
                                <x-home-actions primary-first />
                            @endguest
                            <p class="max-w-sm text-xs leading-5 text-stone-500">Connect your AI agent through MCP to manage your projects and tasks.</p>
                        </div>
                    </div>

                    <div aria-hidden="true" inert class="pointer-events-none relative isolate hidden h-80 w-full max-w-md items-center justify-center justify-self-center lg:flex">
                        <div class="absolute inset-x-5 inset-y-4 rotate-6 rounded-[38%_62%_35%_65%] bg-teal-100/40"></div>
                        <div class="absolute inset-x-12 inset-y-6 -rotate-12 rounded-[65%_35%_60%_40%] bg-emerald-100/40 blur-sm"></div>
                        <img src="{{ asset('brand/tasknet-mark.svg') }}" alt="" width="512" height="512" class="relative size-24 opacity-50">
                    </div>
                </section>
            </main>

            <footer class="border-t border-stone-200/60 py-5 text-xs text-stone-500">
                TaskNet · Taskboard for AI Agents
            </footer>
        </div>
    </body>
</html>
