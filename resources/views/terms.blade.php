<x-app-layout title="Terms" description="The terms for using the TaskNet demonstration service.">
    <div class="flex max-w-3xl flex-col gap-12 pb-6">
        <header class="flex flex-col gap-3">
            <h1 class="text-4xl font-semibold tracking-tight text-stone-900">Terms</h1>
            <p class="text-xl text-stone-600">By using TaskNet you agree to these terms.</p>
        </header>

        <div class="flex flex-col gap-10 border-t border-stone-200 pt-12">
            <section aria-labelledby="using-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="using-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Using TaskNet</h2>
                <p>TaskNet is a demonstration service built to explore the Model Context Protocol (MCP). It is provided for demonstration and testing purposes only. Please do not store sensitive, confidential or important information in it.</p>
            </section>

            <section aria-labelledby="workspaces-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="workspaces-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Demo workspaces</h2>
                <p>Each demo workspace is temporary and limited to {{ config('tasknet.limits.projects') }} projects and {{ config('tasknet.limits.tasks') }} tasks. A workspace and all of its data are deleted automatically {{ config('tasknet.demo.lifetime_days') }} days after you create it. You can also delete a workspace yourself at any time.</p>
            </section>

            <section aria-labelledby="clients-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="clients-heading" class="text-2xl font-semibold tracking-tight text-stone-900">External AI clients</h2>
                <p>You can connect external AI clients to your workspace through MCP. Each client is operated by its provider under that provider’s own terms and privacy policy. You decide which clients to connect and what they may access, and TaskNet cannot control how a provider handles information it receives.</p>
            </section>

            <section aria-labelledby="use-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="use-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Acceptable use</h2>
                <p>Please use TaskNet in good faith. Do not:</p>
                <ul class="flex list-disc flex-col gap-1 pl-6">
                    <li>attempt to access another user’s workspace</li>
                    <li>circumvent security controls or workspace limits</li>
                    <li>overload or disrupt the service</li>
                    <li>use it for unlawful or abusive activity</li>
                </ul>
            </section>

            <section aria-labelledby="availability-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="availability-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Availability</h2>
                <p>TaskNet is provided as is. It may change, become unavailable or be reset at any time, and demo data may not be recoverable.</p>
            </section>

            <section aria-labelledby="privacy-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="privacy-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Privacy</h2>
                <p>See the <a href="{{ route('privacy') }}" class="rounded-sm font-medium text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">privacy notice</a> for how information is handled.</p>
            </section>

            <p class="text-sm text-stone-500">Last updated: 12 October 2026</p>
        </div>
    </div>
</x-app-layout>
