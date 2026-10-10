<x-auth-layout title="Your demo workspace is ready" description="Use these credentials to log in again later from any browser.">
    <dl class="flex flex-col gap-4 rounded-lg border border-stone-200 bg-stone-50 p-4 dark:border-stone-700 dark:bg-stone-950">
        <div class="flex flex-col gap-1">
            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">Demo ID</dt>
            <dd class="font-mono text-lg font-semibold break-all select-all">{{ $credentials['demo_id'] }}</dd>
        </div>
        <div class="flex flex-col gap-1">
            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">Password</dt>
            <dd class="font-mono text-lg font-semibold break-all select-all">{{ $credentials['password'] }}</dd>
        </div>
        <div class="flex flex-col gap-1">
            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">Workspace expires</dt>
            <dd class="text-sm">{{ $expiryLabel }}</dd>
        </div>
    </dl>

    <section aria-labelledby="save-heading" class="flex flex-col gap-3">
        <h2 id="save-heading" class="text-sm font-semibold">1. Save your credentials</h2>
        <p class="text-sm leading-6 text-stone-600 dark:text-stone-400">Save these credentials now. TaskNet stores only a password hash, so your current password cannot be shown or recovered later.</p>

        <div class="flex flex-col gap-3 sm:flex-row">
            <button type="button" data-copy-text="{{ $credentialsText }}" class="inline-flex w-full items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-900 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800">Copy credentials</button>
            <a href="{{ route('demo.credentials.download') }}" data-download-credentials class="inline-flex w-full items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-900 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800">Download login details</a>
        </div>

        <p id="save-status" role="status" class="min-h-5 text-sm font-medium text-teal-800 dark:text-teal-300"></p>
    </section>

    <div class="flex flex-col gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
        <p class="font-semibold">Temporary demo workspace</p>
        <p>TaskNet is for testing MCP connections only. Do not store real projects, confidential information, personal data, or anything important here.</p>
        <p class="font-bold text-red-800 dark:text-red-300">All workspace data will be permanently deleted after {{ config('tasknet.demo.lifetime_days') }} days.</p>
    </div>

    <section aria-labelledby="continue-heading" class="flex flex-col gap-4">
        <h2 id="continue-heading" class="text-sm font-semibold">2. Confirm and continue</h2>

        <form method="POST" action="{{ route('demo.credentials.continue') }}" class="flex flex-col gap-4">
            @csrf
            <div class="flex flex-col gap-2">
                <label for="acknowledge" class="flex items-start gap-3 text-sm leading-6 has-disabled:opacity-60">
                    <input id="acknowledge" type="checkbox" disabled aria-describedby="acknowledge-hint" data-acknowledge="continue-button" class="mt-1 size-4 shrink-0 rounded border-stone-300 accent-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                    <span>I have saved my login credentials and understand that this workspace and all of its data will be permanently deleted on {{ $expiryLabel }}.</span>
                </label>
                <p id="acknowledge-hint" class="text-xs leading-5 text-stone-500 dark:text-stone-400">Copy or download your credentials above to enable this step.</p>
            </div>

            <x-auth.button id="continue-button" disabled>Continue</x-auth.button>
        </form>
    </section>
</x-auth-layout>
