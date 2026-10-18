<x-app-layout title="About" description="TaskNet explores how AI agents can work with a web application through the Model Context Protocol (MCP) under the same rules as the browser.">
    @php
        $textLink = 'rounded-sm text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600';
    @endphp

    <div class="flex flex-col gap-16 pb-6 sm:gap-20">
        <header class="flex max-w-3xl flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-4xl font-semibold tracking-tight text-stone-900">About TaskNet</h1>
                <p class="text-xl font-medium text-teal-700">Taskboard for AI Agents</p>
            </div>

            <div class="flex flex-col gap-4 text-lg leading-8 text-stone-700">
                <p>TaskNet explores how AI agents can work with a web application through the Model Context Protocol (MCP), while following the same permissions, validation and business rules as the browser interface.</p>
                <p>You can manage projects and tasks directly in the browser, or connect an MCP-compatible AI client to work with the same workspace through a controlled set of tools.</p>
            </div>
        </header>

        <figure>
            <div class="rounded-xl border border-stone-200 bg-white p-4 sm:p-6">
                <img src="{{ asset('brand/architecture.svg') }}" alt="TaskNet architecture: the browser, through the Laravel UI, and an AI client, through OAuth and the MCP server, both reach the same shared Laravel layer of actions, policies, validation and limits, which uses PostgreSQL. Events from that layer reach the browser through Reverb and Redis, and the browser refreshes automatically." width="980" height="450" class="hidden h-auto w-full lg:block">
                <svg viewBox="0 0 340 520" role="img" aria-labelledby="diagram-title-m diagram-desc-m" class="mx-auto h-auto w-full max-w-sm font-sans lg:hidden">
                    <title id="diagram-title-m">How the browser and MCP reach the same workspace</title>
                    <desc id="diagram-desc-m">Two paths lead to the same workspace. In the browser, a user works through the TaskNet UI. With an AI client, the client is approved through OAuth and connects through MCP. Both paths pass through the same shared application rules before reaching PostgreSQL. Changes also reach the browser through Reverb and Redis, and it refreshes automatically.</desc>
                    <defs><marker id="arrow-v" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto"><path d="M1 1.5 9 5 1 8.5" fill="none" stroke="#78716c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></marker></defs>
                    <text x="75" y="12" text-anchor="middle" fill="#78716c" font-size="14" font-weight="600">Using the browser</text>
                    <text x="265" y="12" text-anchor="middle" fill="#78716c" font-size="14" font-weight="600">Using an AI client</text>
                    <rect x="0" y="28" width="150" height="44" rx="10" fill="#ffffff" stroke="#d6d3d1" stroke-width="1.25" /><text x="75" y="50" text-anchor="middle" dominant-baseline="central" fill="#292524" font-size="14" font-weight="500">User</text>
                    <rect x="0" y="100" width="150" height="44" rx="10" fill="#ffffff" stroke="#d6d3d1" stroke-width="1.25" /><text x="75" y="122" text-anchor="middle" dominant-baseline="central" fill="#292524" font-size="14" font-weight="500">TaskNet UI</text>
                    <rect x="190" y="28" width="150" height="44" rx="10" fill="#ffffff" stroke="#d6d3d1" stroke-width="1.25" /><text x="265" y="50" text-anchor="middle" dominant-baseline="central" fill="#292524" font-size="14" font-weight="500">AI client</text>
                    <rect x="190" y="100" width="150" height="44" rx="10" fill="#ffffff" stroke="#d6d3d1" stroke-width="1.25" /><text x="265" y="122" text-anchor="middle" dominant-baseline="central" fill="#292524" font-size="14" font-weight="500">OAuth approval</text>
                    <rect x="190" y="172" width="150" height="44" rx="10" fill="#ffffff" stroke="#d6d3d1" stroke-width="1.25" /><text x="265" y="194" text-anchor="middle" dominant-baseline="central" fill="#292524" font-size="14" font-weight="500">MCP</text>
                    <path d="M75 72V98" fill="none" stroke="#a8a29e" stroke-width="1.5" marker-end="url(#arrow-v)" />
                    <path d="M75 144V277" fill="none" stroke="#a8a29e" stroke-width="1.5" marker-end="url(#arrow-v)" />
                    <path d="M265 72V98" fill="none" stroke="#a8a29e" stroke-width="1.5" marker-end="url(#arrow-v)" />
                    <path d="M265 144V170" fill="none" stroke="#a8a29e" stroke-width="1.5" marker-end="url(#arrow-v)" />
                    <path d="M265 216V277" fill="none" stroke="#a8a29e" stroke-width="1.5" marker-end="url(#arrow-v)" />
                    <rect x="0" y="280" width="340" height="70" rx="12" fill="#f0fdfa" stroke="#0d9488" stroke-width="1.5" /><text x="170" y="310" text-anchor="middle" dominant-baseline="central" fill="#134e4a" font-size="15" font-weight="600">Shared application rules</text><text x="170" y="335" text-anchor="middle" dominant-baseline="central" fill="#0f766e" font-size="12">Validation · policies · limits</text>
                    <path d="M265 350V391" fill="none" stroke="#a8a29e" stroke-width="1.5" marker-end="url(#arrow-v)" />
                    <rect x="190" y="394" width="150" height="44" rx="10" fill="#ffffff" stroke="#1c1917" stroke-width="1.5" /><text x="265" y="416" text-anchor="middle" dominant-baseline="central" fill="#1c1917" font-size="14" font-weight="600">PostgreSQL</text>
                    <path d="M75 350V391" fill="none" stroke="#a8a29e" stroke-width="1.5" stroke-dasharray="5 4" marker-end="url(#arrow-v)" />
                    <rect x="0" y="394" width="150" height="44" rx="10" fill="#ffffff" stroke="#d6d3d1" stroke-width="1.25" /><text x="75" y="416" text-anchor="middle" dominant-baseline="central" fill="#292524" font-size="14" font-weight="500">Reverb + Redis</text>
                    <path d="M75 438V469" fill="none" stroke="#a8a29e" stroke-width="1.5" stroke-dasharray="5 4" marker-end="url(#arrow-v)" />
                    <rect x="0" y="472" width="150" height="44" rx="10" fill="#ffffff" stroke="#d6d3d1" stroke-width="1.25" /><text x="75" y="494" text-anchor="middle" dominant-baseline="central" fill="#292524" font-size="14" font-weight="500">Browser refreshes</text>
                </svg>
            </div>
        </figure>

        <section aria-labelledby="how-heading" class="flex flex-col gap-8 border-t border-stone-200 pt-14">
            <h2 id="how-heading" class="text-2xl font-semibold tracking-tight">How it works</h2>

            <dl class="grid gap-8 md:grid-cols-3 md:gap-10">
                <div class="border-l border-teal-600/40 pl-5">
                    <dt class="font-semibold text-stone-900">Secure MCP access</dt>
                    <dd class="mt-2 leading-7 text-stone-600">Connect an AI client through OAuth without sharing your TaskNet password.</dd>
                </div>
                <div class="border-l border-teal-600/40 pl-5">
                    <dt class="font-semibold text-stone-900">Shared application rules</dt>
                    <dd class="mt-2 leading-7 text-stone-600">The browser and MCP use the same authorization, validation, limits and business logic.</dd>
                </div>
                <div class="border-l border-teal-600/40 pl-5">
                    <dt class="font-semibold text-stone-900">Realtime updates</dt>
                    <dd class="mt-2 leading-7 text-stone-600">Changes made through an AI client appear in the browser automatically.</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="connect-heading" class="flex flex-col gap-10">
            <div class="flex max-w-3xl flex-col gap-3">
                <h2 id="connect-heading" class="text-2xl font-semibold tracking-tight">Compatible clients</h2>
                <p class="text-lg leading-8 text-stone-700">TaskNet exposes a standard remote MCP endpoint that compatible AI clients can connect to.</p>
            </div>

            <dl class="grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Claude', 'Custom remote MCP connectors'],
                    ['GitHub Copilot in VS Code', 'MCP tools in agent workflows'],
                    ['Gemini CLI', 'Remote MCP server support'],
                    ['ChatGPT', 'Custom MCP support where available'],
                    ['MCP Inspector', 'MCP server inspection and testing'],
                ] as [$client, $note])
                    <div class="border-t border-stone-200 pt-4">
                        <dt class="font-semibold text-stone-900">{{ $client }}</dt>
                        <dd class="mt-1 leading-7 text-stone-600">{{ $note }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="flex max-w-3xl flex-col gap-3">
                <p class="rounded-lg border border-stone-200 bg-stone-100/70 px-4 py-3 text-sm leading-6 text-stone-700">MCP support and available features vary between clients and subscription plans. Check your AI client’s official documentation to confirm that it supports connecting to custom MCP servers.</p>
                <p class="text-sm leading-6 text-stone-500">Testing locally? A local TaskNet instance can be exposed to remote MCP clients through a secure tunnel such as ngrok or Cloudflare Tunnel. <x-external-link href="https://github.com/seerat89/tasknet/blob/main/docs/development.md#remote-ai-clients-tunnels" class="{{ $textLink }}">See the local testing guide</x-external-link>.</p>
            </div>
        </section>

        <section aria-labelledby="workspace-heading" class="flex flex-col gap-8 border-t border-stone-200 pt-14">
            <h2 id="workspace-heading" class="text-2xl font-semibold tracking-tight">Demo workspace</h2>

            <dl class="grid gap-8 sm:grid-cols-3">
                <div class="flex flex-col gap-1">
                    <dt class="order-2 text-sm text-stone-600">Projects maximum</dt>
                    <dd class="text-3xl font-semibold tracking-tight text-stone-900">{{ config('tasknet.limits.projects') }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="order-2 text-sm text-stone-600">Tasks maximum</dt>
                    <dd class="text-3xl font-semibold tracking-tight text-stone-900">{{ config('tasknet.limits.tasks') }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="order-2 text-sm text-stone-600">Workspace lifetime</dt>
                    <dd class="text-3xl font-semibold tracking-tight text-stone-900">{{ config('tasknet.demo.lifetime_days') }} days</dd>
                </div>
            </dl>

            <p class="max-w-3xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 leading-7 text-amber-900">TaskNet is a demonstration service. Do not use it for sensitive, confidential or important information.</p>
        </section>

        <section aria-labelledby="stack-heading" class="flex flex-col gap-4">
            <h2 id="stack-heading" class="text-2xl font-semibold tracking-tight">Built with</h2>
            <ul class="flex flex-wrap gap-x-3 gap-y-1 leading-7 text-stone-700">
                @foreach (['Laravel', 'PHP', 'Laravel MCP', 'OAuth 2.0 / Passport', 'PostgreSQL', 'Reverb', 'Redis', 'Docker'] as $technology)
                    <li class="after:ml-3 after:text-stone-400 after:content-['·'] last:after:content-none">{{ $technology }}</li>
                @endforeach
            </ul>
        </section>

        <div class="flex flex-col gap-6 border-t border-stone-200 pt-10">
            <p class="flex flex-wrap gap-x-10 gap-y-4 font-medium">
                <x-external-link href="https://github.com/seerat89/tasknet" class="{{ $textLink }}">View source on GitHub</x-external-link>
                <a href="{{ route('privacy') }}" class="{{ $textLink }}">Read the privacy notice</a>
                <a href="{{ route('terms') }}" class="{{ $textLink }}">Read the terms</a>
            </p>
            <p class="text-sm text-stone-500">Built by Seerat Amini · <x-external-link href="https://seerat.me" class="{{ $textLink }}">seerat.me</x-external-link></p>
        </div>
    </div>
</x-app-layout>
