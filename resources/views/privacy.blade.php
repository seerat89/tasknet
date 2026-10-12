<x-app-layout title="Privacy" description="What TaskNet stores, how cookies are used, and how long data is kept.">
    <div class="flex max-w-3xl flex-col gap-12 pb-6">
        <header class="flex flex-col gap-6">
            <h1 class="text-4xl font-semibold tracking-tight text-stone-900">Privacy</h1>

            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 leading-7 text-amber-900">TaskNet is a demonstration service. Do not use it for sensitive, confidential or important information.</p>
        </header>

        <div class="flex flex-col gap-10 border-t border-stone-200 pt-12">
            <section aria-labelledby="stored-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="stored-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Information TaskNet stores</h2>
                <p>TaskNet stores the Demo ID generated for your workspace, a hash of your password (never the password itself), and the projects and tasks you create. If you connect an AI client, it also stores the OAuth records needed to authorize that connection.</p>
                <p>TaskNet does not ask for your name or email address. Basic technical information is processed only to operate the service and protect it from abuse.</p>
            </section>

            <section aria-labelledby="cookies-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="cookies-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Cookies</h2>
                <p>TaskNet sets only the cookies required to keep you signed in and to protect your session. It does not use analytics or advertising cookies.</p>
            </section>

            <section aria-labelledby="retention-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="retention-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Retention</h2>
                <p>Demo workspaces are temporary. A workspace and all of its data are deleted automatically {{ config('tasknet.demo.lifetime_days') }} days after you create it. You can also delete your workspace yourself at any time.</p>
            </section>

            <section aria-labelledby="clients-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                <h2 id="clients-heading" class="text-2xl font-semibold tracking-tight text-stone-900">External AI clients</h2>
                <p>When you connect an external AI client, the information it reads from your workspace is processed by that provider under its own terms and privacy policy. TaskNet cannot control how a provider uses information once it has been sent.</p>
            </section>

            @php
                $hosting = config('tasknet.hosting_provider');
                $database = config('tasknet.database_provider');
            @endphp
            @if ($hosting || $database)
                <section aria-labelledby="providers-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                    <h2 id="providers-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Service providers</h2>
                    <p>TaskNet runs on the following infrastructure.</p>
                    <dl class="flex flex-col gap-1">
                        @if ($hosting)
                            <div class="flex gap-2"><dt class="font-semibold text-stone-900">Hosting:</dt><dd>{{ $hosting }}</dd></div>
                        @endif
                        @if ($database)
                            <div class="flex gap-2"><dt class="font-semibold text-stone-900">Database:</dt><dd>{{ $database }}</dd></div>
                        @endif
                    </dl>
                </section>
            @endif

            @if (config('tasknet.operator_name') || config('tasknet.privacy_contact'))
                <section aria-labelledby="contact-heading" class="flex flex-col gap-3 text-lg leading-8 text-stone-700">
                    <h2 id="contact-heading" class="text-2xl font-semibold tracking-tight text-stone-900">Contact</h2>
                    @if ($operator = config('tasknet.operator_name'))
                        <p>TaskNet is operated by {{ $operator }}.</p>
                    @endif
                    @if ($contact = config('tasknet.privacy_contact'))
                        <p>Privacy questions can be sent to <a href="mailto:{{ $contact }}" class="rounded-sm font-medium text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">{{ $contact }}</a>.</p>
                    @endif
                </section>
            @endif

            <p class="text-sm text-stone-500">Last updated: 12 October 2026</p>
        </div>
    </div>
</x-app-layout>
