<x-app-layout title="Workspace settings">
    <div class="flex max-w-2xl flex-col gap-12 pb-6">
        <header>
            <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Workspace settings</h1>
        </header>

        <section aria-labelledby="workspace-heading" class="flex flex-col gap-4">
            <h2 id="workspace-heading" class="text-xl font-semibold tracking-tight text-stone-900">Workspace</h2>

            <dl class="flex flex-col gap-4 rounded-lg border border-stone-200 bg-white p-5">
                <div class="flex flex-col gap-1">
                    <dt class="text-sm font-medium text-stone-500">Demo ID</dt>
                    <dd class="font-mono text-lg font-semibold break-all">{{ $user->demo_id }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-sm font-medium text-stone-500">Expires</dt>
                    <dd class="text-stone-700">{{ $user->expires_at->utc()->format('F j, Y \a\t H:i') }} UTC · {{ $user->demoDaysRemaining() }} {{ Str::plural('day', $user->demoDaysRemaining()) }} left</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="clients-heading" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <h2 id="clients-heading" class="text-xl font-semibold tracking-tight text-stone-900">Authorized MCP clients</h2>
                <p class="leading-7 text-stone-700">Clients you’ve approved to access this workspace through MCP.</p>
            </div>

            @if ($clients->isEmpty())
                <div class="flex flex-col gap-1 rounded-lg border border-stone-200 bg-white p-5">
                    <p class="font-medium text-stone-900">No MCP clients are currently authorized.</p>
                    <p class="text-sm leading-6 text-stone-600">Connect a compatible MCP client and approve access to see it here.</p>
                </div>
            @else
                <ul class="flex flex-col divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white">
                    @foreach ($clients as $client)
                        @php($clientName = filled($client->name) ? $client->name : 'MCP client')
                        <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="font-semibold break-words text-stone-900">{{ $clientName }}</p>

                            <x-confirm-delete
                                :name="'revoke-'.$client->getKey()"
                                :heading="'Revoke access for '.$clientName.'?'"
                                message="This client will no longer be able to access this workspace. You can authorize it again later."
                                :action="route('settings.clients.destroy', $client->getKey())"
                                label="Revoke access"
                                class="w-fit"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="danger-heading" class="flex flex-col gap-4 rounded-lg border border-red-200 bg-red-50/50 p-5">
            <div class="flex flex-col gap-1">
                <h2 id="danger-heading" class="text-xl font-semibold tracking-tight text-red-900">Danger zone</h2>
                <p class="leading-7 text-stone-700">Delete this demo workspace and its TaskNet data permanently.</p>
            </div>

            <details class="group flex flex-col gap-4" @if ($errors->has('confirmation')) open @endif>
                <summary class="w-fit cursor-pointer list-none rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 group-open:hidden">Delete workspace</summary>

                <form method="POST" action="{{ route('workspace.destroy') }}" class="flex flex-col gap-4 rounded-lg border border-red-200 bg-white p-5">
                    @csrf
                    @method('DELETE')

                    <div class="flex flex-col gap-1">
                        <h3 class="text-lg font-semibold tracking-tight text-stone-900">Delete workspace permanently?</h3>
                        <p class="text-sm leading-6 text-stone-600">This removes this demo workspace and its associated TaskNet data. This action cannot be undone.</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="confirmation" class="text-sm font-medium text-stone-900">Type <span class="font-mono font-semibold">{{ $user->demo_id }}</span> to confirm.</label>
                        <input id="confirmation" name="confirmation" type="text" required autocomplete="off" autocapitalize="characters" spellcheck="false" @error('confirmation') aria-invalid="true" aria-describedby="confirmation-error" @enderror class="w-full rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                        @error('confirmation')
                            <p id="confirmation-error" role="alert" class="text-sm font-medium text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <button type="submit" data-loading-text="Deleting…" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">Delete workspace permanently</button>
                    </div>
                </form>
            </details>
        </section>
    </div>
</x-app-layout>
