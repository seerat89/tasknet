<x-app-layout title="Connect your AI agent. Test MCP." description="TaskNet is a temporary project and task sandbox for testing how AI agents interact with an application through MCP.">
    <section aria-labelledby="hero-heading" class="my-auto grid w-full items-center gap-12 lg:grid-cols-[1fr_1.15fr] lg:gap-12">
        <div class="flex flex-col items-start gap-8">
            <div class="flex flex-col gap-6">
                <h1 id="hero-heading" class="text-[clamp(1.75rem,7vw,2.5rem)] leading-[1.1] font-bold tracking-tight sm:text-5xl lg:text-[2.5rem] xl:text-[3rem]">
                    <span class="block">Connect your AI agent.</span>
                    <span class="block text-teal-700">Test MCP.</span>
                </h1>
                <p class="max-w-md text-lg leading-8 text-stone-600">TaskNet is a temporary project and task sandbox for testing how AI agents interact with an application through MCP.</p>
            </div>

            <div class="flex flex-col gap-4">
                @guest
                    <x-home-actions primary-first />
                @endguest
                @auth
                    <x-link-button :href="route('dashboard')" class="min-w-40">Open dashboard</x-link-button>
                @endauth
                <p class="max-w-sm text-xs leading-5 text-stone-500">Temporary workspace · automatically deleted after {{ config('tasknet.demo.lifetime_days') }} days</p>
            </div>
        </div>

        <div aria-hidden="true" inert class="pointer-events-none relative isolate hidden h-80 w-full max-w-md items-center justify-center justify-self-center lg:flex">
            <div class="absolute inset-x-5 inset-y-4 rotate-6 rounded-[38%_62%_35%_65%] bg-teal-100/40"></div>
            <div class="absolute inset-x-12 inset-y-6 -rotate-12 rounded-[65%_35%_60%_40%] bg-emerald-100/40 blur-sm"></div>
            <img src="{{ asset('brand/tasknet-mark.svg') }}" alt="" width="512" height="512" class="relative size-24 opacity-50">
        </div>
    </section>
</x-app-layout>
