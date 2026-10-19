<x-app-layout title="Your demo workspace is ready">
    <div class="flex max-w-xl flex-col gap-8 pb-4">
        <header class="flex flex-col gap-3">
            <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Your demo workspace is ready</h1>
            <p class="leading-7 text-stone-700">You’re signed in now. Save these login details so you can return after signing out or from another browser.</p>
        </header>

        <div role="alert" class="flex flex-col gap-1 rounded-lg border-2 border-red-300 bg-red-50 px-5 py-4 text-red-950">
            <p class="text-lg font-bold">Save your password now. It cannot be recovered.</p>
            <p class="leading-7">TaskNet stores only a hash of your password and has no password reset. This is the only time it is shown. If you lose it, you will not be able to sign in again after your current session ends.</p>
        </div>

        <section aria-labelledby="credentials-heading" class="flex flex-col gap-4">
            <h2 id="credentials-heading" class="sr-only">Login details</h2>

            <dl class="flex flex-col gap-4 rounded-lg border border-stone-200 bg-white p-5">
                <div class="flex flex-col gap-1">
                    <dt class="text-sm font-medium text-stone-500">Demo ID</dt>
                    <dd class="font-mono text-lg font-semibold break-all select-all">{{ $credentials['demo_id'] }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm font-medium text-stone-500">Password</dt>
                    <dd class="font-mono text-lg font-semibold break-all select-all">{{ $credentials['password'] }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm font-medium text-stone-500">Workspace expires</dt>
                    <dd class="text-sm text-stone-700">{{ $expiryLabel }}</dd>
                </div>
            </dl>

            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" data-copy-text="{{ $credentialsText }}" class="inline-flex w-full items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-900 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Copy credentials</button>
                <a href="{{ route('demo.credentials.download') }}" data-download-credentials class="inline-flex w-full items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-900 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Download login details</a>
            </div>

            <p id="save-status" role="status" class="min-h-5 text-sm font-medium text-teal-800"></p>
        </section>

        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">TaskNet is a demonstration service. Do not use it for sensitive, confidential or important information. This workspace and its data are deleted automatically {{ config('tasknet.demo.lifetime_days') }} days after you create it.</p>

        <form method="POST" action="{{ route('demo.credentials.continue') }}">
            @csrf
            <x-auth.button class="sm:w-auto sm:min-w-56" data-loading-text="Opening workspace…">Continue to workspace</x-auth.button>
        </form>
    </div>
</x-app-layout>
