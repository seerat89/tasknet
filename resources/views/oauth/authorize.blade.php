<x-auth-layout title="Connect an AI client" description="An external AI client wants to use TaskNet on your behalf.">
    <dl class="flex flex-col gap-4 rounded-lg border border-stone-200 bg-stone-50 p-4 dark:border-stone-700 dark:bg-stone-950">
        <div class="flex flex-col gap-1">
            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">Signed in as</dt>
            <dd class="font-mono text-lg font-semibold break-all">{{ $user->demo_id }}</dd>
        </div>
        <div class="flex flex-col gap-1">
            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">AI client requesting access</dt>
            <dd class="text-lg font-semibold break-words">{{ $client->name }}</dd>
        </div>
    </dl>

    <section aria-labelledby="access-heading" class="flex flex-col gap-3">
        <h2 id="access-heading" class="text-sm font-semibold">If you approve, this client can</h2>
        <ul class="flex list-disc flex-col gap-1 pl-5 text-sm leading-6 text-stone-600 dark:text-stone-400">
            <li>View your projects and tasks</li>
            <li>Create and update projects and tasks</li>
        </ul>
        <p class="text-sm leading-6 text-stone-600 dark:text-stone-400">It cannot delete anything, see other workspaces, or learn your Demo ID password. It follows the same limits as the TaskNet web app.</p>
    </section>

    <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">Only approve if you just started this connection from your own AI client.</p>

    <div class="flex flex-col gap-3 sm:flex-row-reverse">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="sm:flex-1">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <x-auth.button>Approve</x-auth.button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="sm:flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-900 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800">Deny</button>
        </form>
    </div>
</x-auth-layout>
