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
                <p><a href="{{ route('connect') }}" class="rounded-sm text-sm font-medium text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">How to connect an AI client</a></p>
            </div>

            @if ($clients->isEmpty())
                <div class="flex flex-col gap-1 rounded-lg border border-stone-200 bg-white p-5">
                    <p class="font-medium text-stone-900">No MCP clients are currently authorized.</p>
                    <p class="text-sm leading-6 text-stone-600">Connect a compatible MCP client and approve access to see it here.</p>
                </div>
            @else
                <ul class="flex flex-col divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white">
                    @foreach ($clients as $client)
                        <li class="flex flex-col gap-4 p-5">
                            <div class="flex items-start gap-3">
                                <x-mcp-client-icon :provider="$client->provider" />
                                <div class="flex min-w-0 flex-col">
                                    <h3 class="font-semibold break-words text-stone-900">{{ $client->name }}</h3>
                                    <p class="text-sm text-stone-600">
                                        @if ($client->provider)
                                            Returns to {{ $client->provider->host() }} after sign-in
                                        @else
                                            Service not identified
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                <div class="flex flex-col gap-0.5">
                                    <dt class="font-medium text-stone-500">Approved</dt>
                                    <dd class="text-stone-800">
                                        @if ($client->authorizedAt)
                                            <x-utc-time :value="$client->authorizedAt" />
                                        @else
                                            Not recorded
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <dt class="font-medium text-stone-500">Last activity</dt>
                                    <dd class="text-stone-800">
                                        @if ($client->lastUsedAt)
                                            <x-utc-time :value="$client->lastUsedAt" />
                                        @else
                                            None recorded
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <dt class="font-medium text-stone-500">Status</dt>
                                    <dd class="text-stone-800">
                                        <span class="font-semibold">{{ $client->hasAccessToken ? 'Access active' : 'Renews on next use' }}</span>
                                        @if ($client->accessUntil)
                                            <span class="block text-stone-600">
                                                @if ($client->accessUntil->greaterThanOrEqualTo($user->expires_at))
                                                    Ends when this workspace expires
                                                @else
                                                    Ends <x-utc-time :value="$client->accessUntil" /> unless renewed
                                                @endif
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <dt class="font-medium text-stone-500">Permissions</dt>
                                    <dd class="text-stone-800"><x-mcp-permissions :scopes="$client->scopes" /></dd>
                                </div>
                            </dl>

                            <x-confirm-delete
                                :name="'revoke-'.$client->id"
                                :heading="'Revoke access for '.$client->name.'?'"
                                message="Its tokens stop working immediately and it cannot renew them, so its chats lose access to TaskNet. Your projects and tasks are kept. To reconnect, you will need to approve it again. You can also remove the TaskNet connection inside that client."
                                :action="route('settings.clients.destroy', $client->id)"
                                label="Revoke access"
                                loading-text="Revoking…"
                                :aria-label="'Revoke access for '.$client->name"
                                class="w-fit"
                            />
                        </li>
                    @endforeach
                </ul>

                <p class="text-sm leading-6 text-stone-600">Clients choose their own names. No client can delete anything or reach another workspace. “Not recorded” means TaskNet has no date for an approval, for example one given before it began keeping them. Last activity is the latest successful tool call or view, and can be up to {{ config('tasknet.mcp.activity_interval_minutes') }} minutes behind.</p>
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
