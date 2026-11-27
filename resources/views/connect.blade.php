<x-app-layout title="Connect an AI client" description="Connect ChatGPT, Claude or another MCP client to your TaskNet workspace: server address, steps, sign-in, example prompts and troubleshooting.">
    @php
        $textLink = 'rounded-sm font-medium text-teal-700 underline decoration-teal-700/40 underline-offset-4 hover:decoration-teal-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600';
        $section = 'flex flex-col gap-4 border-t border-stone-200 pt-10';
        $heading = 'text-2xl font-semibold tracking-tight';
        $steps = 'flex list-decimal flex-col gap-2 pl-6 leading-7 text-stone-700';
        $public = str_starts_with($serverUrl, 'https://');
        $oauth = config('tasknet.mcp.oauth');
    @endphp

    <div class="flex max-w-3xl flex-col gap-10 pb-6">
        <header class="flex flex-col gap-4">
            <h1 class="text-4xl font-semibold tracking-tight text-stone-900">Connect an AI client</h1>
            <p class="text-lg leading-8 text-stone-700">Link an AI assistant to your TaskNet workspace to list, create and update projects and tasks by asking in plain language, or to browse and edit them in interactive views inside the chat. It reaches only your own workspace, and it cannot delete anything.</p>
        </header>

        <section aria-labelledby="url-heading" class="flex flex-col gap-3 rounded-lg border border-stone-200 bg-white p-5">
            <h2 id="url-heading" class="text-lg font-semibold tracking-tight">MCP server URL</h2>
            <p class="rounded-lg bg-stone-100 px-3 py-2.5 font-mono text-sm break-all select-all">{{ $serverUrl }}</p>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <button type="button" data-copy-text="{{ $serverUrl }}" data-copy-success="Server URL copied" data-copy-failure="Copy failed. Select the URL and copy it manually." class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-900 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Copy server URL</button>
                <p id="save-status" role="status" class="min-h-5 text-sm font-medium text-teal-800"></p>
            </div>
            @unless ($public)
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">This address does not use HTTPS. Cloud assistants such as ChatGPT and Claude need a public HTTPS address, so they cannot connect to it. If you run TaskNet yourself, set it up behind a tunnel first.</p>
            @endunless
        </section>

        <section aria-labelledby="before-heading" class="flex flex-col gap-3">
            <h2 id="before-heading" class="{{ $heading }}">Before you start</h2>
            <ul class="flex list-disc flex-col gap-1 pl-6 leading-7 text-stone-700">
                <li>
                    A TaskNet demo workspace.
                    @guest
                        <a href="{{ route('demo.create') }}" class="{{ $textLink }}">Create one</a> and save its Demo ID and password.
                    @else
                        You are signed in to {{ auth()->user()->demo_id }}.
                    @endguest
                </li>
                <li>An AI client that supports remote MCP servers with OAuth sign-in. Support depends on the client and your plan.</li>
            </ul>
            <nav aria-label="On this page">
                <ul class="flex flex-wrap gap-x-5 gap-y-2 pt-2 text-sm">
                    @foreach (['chatgpt' => 'ChatGPT', 'claude' => 'Claude', 'other-clients' => 'Other clients', 'sign-in' => 'Sign-in', 'examples' => 'Example prompts', 'permissions' => 'Permissions', 'troubleshooting' => 'Troubleshooting'] as $anchor => $label)
                        <li><a href="#{{ $anchor }}" class="{{ $textLink }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </section>

        <section id="chatgpt" aria-labelledby="chatgpt-heading" class="{{ $section }}">
            <h2 id="chatgpt-heading" class="{{ $heading }}">ChatGPT</h2>
            <p class="leading-7 text-stone-700">OpenAI documents custom MCP servers for ChatGPT on the web, not on mobile. Its help article says full MCP support is available to Business, Enterprise and Edu customers, and that Pro users can connect custom MCP servers with read/fetch permissions. In Business workspaces only admins and owners can create and publish them, and Enterprise and Edu admins can give members permission to create connections. Account and workspace policies also apply.</p>
            <ol class="{{ $steps }}">
                <li>In ChatGPT on the web, open <x-external-link href="https://chatgpt.com/plugins" class="{{ $textLink }}">Plugins</x-external-link>.</li>
                <li>Select the plus button, then <strong>Add custom MCP server</strong>.</li>
                <li>Enter a name, such as TaskNet, and a description if you like.</li>
                <li>Under <strong>Connection</strong>, enter the server URL above, including <code class="font-mono text-sm">/mcp</code>.</li>
                <li>For authentication, choose <strong>OAuth</strong>, which TaskNet uses. Read the risk warning and select <strong>I understand and want to continue</strong>.</li>
                <li>Select <strong>Create as a plugin</strong>. When TaskNet opens, <a href="#sign-in" class="{{ $textLink }}">sign in and approve</a>.</li>
                <li>Install the plugin, start a new chat, type <strong>@</strong> and select it.</li>
            </ol>
            <p class="leading-7 text-stone-700">ChatGPT may ask you to confirm actions that change data, depending on your settings. After TaskNet’s tools change, open the connection in Plugins and select <strong>Refresh</strong>. For an app published to a workspace, an admin can refresh it from the workspace’s app settings.</p>
            <p class="text-sm leading-6 text-stone-600">OpenAI’s documentation: <x-external-link href="https://developers.openai.com/plugins/deploy/connect-chatgpt" class="{{ $textLink }}">connect a server to ChatGPT</x-external-link> and <x-external-link href="https://help.openai.com/en/articles/12584461-developer-mode-apps-and-full-mcp-connectors-in-chatgpt-beta" class="{{ $textLink }}">plan availability</x-external-link>.</p>
        </section>

        <section id="claude" aria-labelledby="claude-heading" class="{{ $section }}">
            <h2 id="claude-heading" class="{{ $heading }}">Claude</h2>
            <p class="leading-7 text-stone-700">Anthropic documents custom connectors for Free, Pro, Max, Ultra, Team and Enterprise plans; Free users are limited to one. Claude connects from Anthropic’s servers, so the server URL must be reachable from the public internet over HTTPS on port 443.</p>

            <h3 class="font-semibold text-stone-900">Individual plans</h3>
            <ol class="{{ $steps }}">
                <li>Open <x-external-link href="https://claude.ai/customize/connectors" class="{{ $textLink }}">Customize &gt; Connectors</x-external-link>.</li>
                <li>Select <strong>+ Add</strong>, then <strong>Add custom connector</strong>.</li>
                <li>Enter a name and the server URL above, then select <strong>Continue</strong>.</li>
                <li>Review the detected settings and select <strong>Continue</strong>. Under <strong>Authentication</strong> use <strong>Sign in now</strong>. Under <strong>OAuth client</strong> use <strong>Register automatically</strong>: the option Claude recommends, its published identity, needs support that TaskNet does not offer.</li>
                <li>Select <strong>Add</strong>. When TaskNet opens, <a href="#sign-in" class="{{ $textLink }}">sign in and approve</a>.</li>
                <li>In a chat, select <strong>+</strong> in the lower left, then <strong>Connectors</strong>, and turn TaskNet on.</li>
            </ol>

            <h3 class="font-semibold text-stone-900">Team and Enterprise plans</h3>
            <p class="leading-7 text-stone-700">An owner first adds the connector under <strong>Organization settings &gt; Connectors</strong>: <strong>Add</strong>, then <strong>Custom</strong> and <strong>Web</strong>. Each member then opens <strong>Customize &gt; Connectors</strong>, finds the connector, usually labelled Custom, and selects <strong>Connect</strong>.</p>
            <p class="leading-7 text-stone-700">To remove it from your own account, select it under Customize &gt; Connectors, open the three-dot menu and select <strong>Remove</strong>.</p>
            <p class="text-sm leading-6 text-stone-600">Anthropic’s documentation: <x-external-link href="https://claude.com/docs/connectors/custom/remote-mcp" class="{{ $textLink }}">add a custom connector</x-external-link>.</p>
        </section>

        <section id="other-clients" aria-labelledby="other-heading" class="{{ $section }}">
            <h2 id="other-heading" class="{{ $heading }}">Other MCP clients</h2>
            <p class="leading-7 text-stone-700">Add TaskNet as a remote MCP server using your client’s own instructions. It needs to support:</p>
            <dl class="grid gap-x-6 gap-y-3 rounded-lg border border-stone-200 bg-white p-5 text-sm sm:grid-cols-[auto_1fr]">
                @foreach ([
                    'Server URL' => $serverUrl,
                    'Transport' => 'Streamable HTTP',
                    'Sign-in' => 'OAuth authorization code with PKCE (S256)',
                    'Client registration' => 'Dynamic client registration; no client secret',
                    'Scope' => 'mcp:use',
                ] as $term => $value)
                    <dt class="font-medium text-stone-500">{{ $term }}</dt>
                    <dd class="break-all text-stone-900">{{ $value }}</dd>
                @endforeach
            </dl>
            <p class="leading-7 text-stone-700">TaskNet only registers clients whose sign-in callback address this server allows, so not every client can connect.</p>
        </section>

        <section id="sign-in" aria-labelledby="sign-in-heading" class="{{ $section }}">
            <h2 id="sign-in-heading" class="{{ $heading }}">Sign-in and approval</h2>
            <ol class="{{ $steps }}">
                <li>Your client opens TaskNet in the browser. If asked, enter your Demo ID and password. You type them only on TaskNet; the client never receives them.</li>
                <li>Check the client’s name and the address it returns to. Select <strong>Approve</strong> only if you have just started this connection yourself.</li>
                <li>The client receives its own tokens. An access token lasts {{ $oauth['access_token_days'] }} {{ Str::plural('day', $oauth['access_token_days']) }} and the client can renew it. If it goes {{ $oauth['refresh_token_days'] }} days without renewing, or your workspace expires, you need to connect again.</li>
            </ol>
        </section>

        <section id="examples" aria-labelledby="examples-heading" class="{{ $section }}">
            <h2 id="examples-heading" class="{{ $heading }}">Example prompts</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                    <h3 class="font-semibold text-stone-900">Ask in conversation</h3>
                    <ul class="flex list-disc flex-col gap-2 pl-5 leading-7 text-stone-700">
                        <li>“List my TaskNet projects.”</li>
                        <li>“Create a project called Website refresh.”</li>
                        <li>“Add a high-priority task to Website refresh: draft the homepage copy, due next Friday.”</li>
                        <li>“Which tasks in Website refresh are still to do?”</li>
                        <li>“Mark the homepage copy task as completed.”</li>
                    </ul>
                </div>
                <div class="flex flex-col gap-2">
                    <h3 class="font-semibold text-stone-900">Open an interactive view</h3>
                    <ul class="flex list-disc flex-col gap-2 pl-5 leading-7 text-stone-700">
                        <li>“Show my TaskNet projects.”</li>
                        <li>“Show the tasks in Website refresh.”</li>
                        <li>“Open a form for a new TaskNet project.”</li>
                        <li>“Open a form to add a task to Website refresh.”</li>
                    </ul>
                </div>
            </div>
            <p class="text-sm leading-6 text-stone-600">Interactive views need a client that supports MCP Apps. They have been tested in ChatGPT and Claude on the web. Elsewhere, the conversational requests still work if the client can use MCP tools.</p>
        </section>

        <section id="permissions" aria-labelledby="permissions-heading" class="{{ $section }}">
            <h2 id="permissions-heading" class="{{ $heading }}">Permissions and removing access</h2>
            <p class="leading-7 text-stone-700">An approved client can:</p>
            <x-mcp-permissions class="leading-7 text-stone-700" />
            <p class="leading-7 text-stone-700">It cannot delete anything, see other workspaces, or learn your Demo ID password. It follows the same limits as the TaskNet web app. Whatever it reads is also handled by its provider under that provider’s terms; see the <a href="{{ route('privacy') }}" class="{{ $textLink }}">privacy notice</a>.</p>
            <p class="leading-7 text-stone-700">
                To remove access, open
                @auth
                    <a href="{{ route('settings') }}" class="{{ $textLink }}">Settings</a>,
                @else
                    Settings after <a href="{{ route('login') }}" class="{{ $textLink }}">signing in</a>,
                @endauth
                find the client under Authorized MCP clients and select <strong>Revoke access</strong>. Its tokens stop working immediately.
            </p>
        </section>

        <section id="troubleshooting" aria-labelledby="troubleshooting-heading" class="{{ $section }}">
            <h2 id="troubleshooting-heading" class="{{ $heading }}">Troubleshooting</h2>
            <dl class="flex flex-col divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white">
                @foreach ([
                    'The client cannot reach the server' => 'Check that you copied the whole URL, ending in /mcp. A cloud assistant needs a public HTTPS address; a localhost address works only for a client running on the same computer.',
                    'Registration fails, or the error mentions invalid_redirect_uri' => 'This TaskNet server does not allow that client’s callback address. If you run the server, add the address to MCP_REDIRECT_DOMAINS and restart it.',
                    'You cannot sign in' => 'Use the Demo ID and password shown when the workspace was created. Passwords cannot be recovered. If the workspace has expired, create a new one and connect again.',
                    'It worked before and now reports an authorization error' => 'Access may have been revoked, lapsed after a long break, or ended with the workspace. Remove the connection in your client and add it again.',
                    'Too many requests (error 429)' => 'Each workspace allows '.config('tasknet.mcp.requests_per_minute').' MCP requests per minute, shared by the assistant and interactive views. Wait a minute and try again.',
                    'No interactive view appears' => 'Ask explicitly, for example “Show my TaskNet projects”, and check that TaskNet is turned on for the chat. The client may not support MCP Apps.',
                    'The client shows old tools or an old view' => 'In ChatGPT, select Refresh on the connection’s page in Plugins. Otherwise remove the connection and add it again, then start a new chat.',
                    'Reading works but creating or updating does not' => 'The action may be waiting for your confirmation or have been declined. Your AI client’s plan, account or workspace settings can also restrict it; see the notes for your client above.',
                ] as $problem => $remedy)
                    <div class="flex flex-col gap-1 p-4">
                        <dt class="font-semibold text-stone-900">{{ $problem }}</dt>
                        <dd class="leading-7 text-stone-700">{{ $remedy }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="text-sm leading-6 text-stone-500">Menus and plan availability change. These steps were checked against OpenAI’s and Anthropic’s documentation in November 2026.</p>
        </section>
    </div>
</x-app-layout>
