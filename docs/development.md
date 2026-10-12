# Development setup

TaskNet runs locally with Docker Compose. Docker provides reproducible PHP 8.5, PostgreSQL 18 and Node 24 versions for the whole team, so nothing but Docker needs to be installed. Production uses the same technology family (PHP and PostgreSQL) on Vercel with Neon PostgreSQL. Keep the repository on your normal filesystem; Docker is only the runtime.

Requirements: Docker Desktop or Docker Engine with the Compose plugin.

## First start

```bash
git clone https://github.com/seerat89/tasknet.git
cd tasknet
cp .env.example .env        # Windows PowerShell: Copy-Item .env.example .env
docker compose up -d --build
```

Open http://localhost:8000. Give the first start a minute or two while dependencies install; follow progress with `docker compose logs -f`.

## What `docker compose up` does

In order (see `compose.yaml`):

1. `postgres` starts; `app` waits until its healthcheck passes.
2. `app` runs `composer install` if `vendor/autoload.php` is missing.
3. `app` runs `php artisan key:generate` if `APP_KEY` in `.env` is empty.
4. `app` runs `php artisan migrate --force`, then starts `php artisan serve`.
5. `node` independently runs `npm install` if `node_modules` is missing, then starts Vite.

Dependency installation and key generation are skipped when already complete. Database migrations run safely on each startup.

## Services

| Service    | Purpose                  | Port                                            |
|------------|--------------------------|-------------------------------------------------|
| `app`      | PHP 8.5, `artisan serve` | 8000                                            |
| `postgres` | PostgreSQL 18            | 5434 on the host, `postgres:5432` inside Docker |
| `node`     | Node 24, Vite dev server | 5173                                            |
| `reverb`   | Laravel Reverb WebSockets | 8080                                           |

Laravel inside Docker connects with `DB_HOST=postgres` and `DB_PORT=5432`.

## Docker volumes

- `postgres-data`: database files.
- `vendor`: PHP dependencies.
- `node-modules`: npm dependencies.

`vendor/` and `node_modules/` live in Docker volumes to avoid slow Windows/macOS bind-mount access and to keep Linux-native packages out of your host checkout. Those folders on your host stay empty, so use the containers for Composer, npm and tests.

## Database tools

DBeaver, pgAdmin and similar tools can connect with:

- Host: `localhost`
- Port: `5434`
- Database: `tasknet`
- Username: `tasknet_app`
- Password: `DB_PASSWORD` from your `.env`

The `.env` file holds a local-only development password. Never reuse it elsewhere or commit `.env`.

TaskNet intentionally does not persist IP addresses or User-Agent strings in application session records.

## Everyday commands

```bash
docker compose run --rm app php artisan <command>     # Artisan
docker compose run --rm app composer <command>        # Composer
docker compose run --rm node npm <command>            # npm, e.g. npm run build
docker compose run --rm app php artisan test          # Test suite
docker compose logs -f                                # Follow logs
```

After changing `composer.json` or `package.json`, reinstall dependencies:

```bash
docker compose run --rm app composer install
docker compose run --rm node npm install
```

If you run a command before the first `docker compose up`, `vendor` is still empty; run `docker compose run --rm app composer install` first.

## MCP and OAuth

TaskNet exposes an MCP server at http://localhost:8000/mcp (production: https://tasknet.seerat.me/mcp). Remote clients authenticate with OAuth through Laravel Passport:

1. The client calls `/mcp` and receives `401` with a `WWW-Authenticate` header pointing at `/.well-known/oauth-protected-resource/mcp`.
2. It reads the OAuth metadata (`/.well-known/oauth-authorization-server`) and registers itself as a public client at `POST /oauth/register`.
3. It sends the user to `/oauth/authorize` (authorization code with PKCE, scope `mcp:use`). The user signs in with their Demo ID and password on TaskNet and sees an Approve/Deny screen. The AI client never sees the password.
4. The client exchanges the code at `/oauth/token` for an access token (valid one day) and a refresh token.
5. Each `/mcp` request runs as that user. Expired demo workspaces are rejected even with a valid token.

Only the authorization code and refresh token grants are enabled. MCP tools reuse the same actions and policies as the web UI, so the 10-project and 100-task limits apply. They never accept `user_id`, `demo_id` or `email` as input.

Tools: `list_projects`, `get_project`, `list_tasks` and `get_task` are advertised directly. `create_project`, `update_project`, `create_task` and `update_task` are in a searchable catalog (see [Progressive tool disclosure](#progressive-tool-disclosure)). There are no delete tools yet.

### Progressive tool disclosure

A client normally receives every tool definition and schema up front, which costs tokens on every conversation and grows with the server. TaskNet demonstrates the alternative with Laravel MCP's `ToolSearch` catalog (`app/Mcp/Servers/TaskNetServer.php`):

- Frequently used read tools are advertised directly in `tools/list`.
- Less frequent mutation tools are registered under `ToolSearch::class`. They are not in `tools/list` and cannot be called directly. Laravel MCP adds two tools instead: `search_tools` returns matching tools with their exact names and full input schemas, and `execute_tools` runs one or more of them in order.
- The catalog changes nothing about security: catalog tools run as the same OAuth user, use the same actions and policies, and are subject to the same limits and workspace expiry.

With only eight tools this is not needed. It is included to show a design that scales: as a server grows, clients do not need every schema up front. `tools/list` returns `list_projects`, `get_project`, `list_tasks`, `get_task`, `search_tools`, `execute_tools`.

OAuth signing keys are generated into `storage/oauth-private.key` and `storage/oauth-public.key` on first start. They are git-ignored; never commit them. For Vercel, set `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` environment variables instead. To rotate keys locally, run `docker compose run --rm app php artisan passport:keys --force` (existing tokens stop working).

### Testing with MCP Inspector

The official [MCP Inspector](https://github.com/modelcontextprotocol/inspector) is the quickest way to check the OAuth flow, tool discovery, schemas and tool execution. It needs Node on your machine and runs outside Docker:

1. Start TaskNet: `docker compose up -d`.
2. Start the Inspector:

   ```bash
   npx @modelcontextprotocol/inspector --server-url http://localhost:8000/mcp --transport http
   ```

3. Connect to `http://localhost:8000/mcp`. When the Inspector sends you to TaskNet, sign in with a demo workspace's Demo ID and password (create one on the home page) and approve the connection.
4. Open the Tools tab to see the directly advertised tools and their schemas, then run `list_projects` or `get_task`. Use `search_tools` (for example with the query `create task`) to find the catalog tools, then `execute_tools` with `{"calls":[{"name":"create_task","arguments":{...}}]}`.

The Inspector validates exactly what a real AI client sees: the OAuth handshake, the MCP connection, discovery, input schemas and tool results.

### Testing with remote AI clients (tunnels)

Tools that run on your machine, such as the MCP Inspector, can use `http://localhost:8000/mcp` directly. Browser-based and cloud AI clients cannot reach `localhost`, so to try one of them against a local TaskNet you need a public HTTPS address that forwards to your machine. A tunnel such as [ngrok](https://ngrok.com/) or [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) provides one. Either works; TaskNet does not depend on a particular provider.

1. **Start TaskNet and a tunnel.** Run `docker compose up -d`, then forward port 8000, for example:

   ```bash
   ngrok http 8000
   # or
   cloudflared tunnel --url http://localhost:8000
   ```

   The tool prints an `https://` address. The steps below call it `https://example.tunnel`.

2. **Point TaskNet at it.** In `.env` set `APP_URL=https://example.tunnel`, then `docker compose restart app`. OAuth discovery and redirect URLs are built from `APP_URL`.

3. **Allow the client's callback.** Dynamic client registration only accepts redirect URIs on origins listed in `MCP_REDIRECT_DOMAINS` (loopback addresses are always accepted while `http://localhost` is listed). Add the callback origin documented by the client you are testing, for example:

   ```dotenv
   MCP_REDIRECT_DOMAINS=http://localhost,http://127.0.0.1,https://client.example.com
   ```

   Restart the app afterwards. Clients that use a custom URL scheme need that scheme added to `custom_schemes` in `config/mcp.php`.

4. **Check the endpoint.**

   ```bash
   curl -i https://example.tunnel/mcp
   curl https://example.tunnel/.well-known/oauth-authorization-server
   ```

   The first should return `401` with a `WWW-Authenticate` header. The second should return JSON whose URLs all start with `https://`.

5. **Connect the client.** Use `https://example.tunnel/mcp` as the MCP server URL. When the client sends you to TaskNet, sign in with a demo workspace's Demo ID and approve the connection.

Keep the dashboard open on `http://localhost:8000` to watch realtime updates; the browser reaches Reverb on `localhost:8080`, which a tunnel does not forward.

Troubleshooting:

- `invalid_redirect_uri` when the client registers: its callback origin is not in `MCP_REDIRECT_DOMAINS` (step 3).
- `http://` URLs in the OAuth metadata: TaskNet does not configure trusted proxies yet, so behind a tunnel it may not detect HTTPS. Check the metadata from step 4 before blaming the client.
- `429`: the per-workspace MCP rate limit (`MCP_RATE_LIMIT`) was reached.
- `403` on `/mcp`: the demo workspace has expired or the token lacks the `mcp:use` scope.

Notes:

- Tunnels are for development and testing, not for hosting TaskNet. Stop the tunnel when you are done and set `APP_URL` back to `http://localhost:8000`.
- Anyone with the tunnel address can reach your local instance, and the development `.env` has `APP_DEBUG=true`. Set it to `false` while a tunnel is open and only use throwaway demo data.
- Client support for custom MCP servers differs by product and plan; check the client's own documentation.

### OAuth token lifetimes and cleanup

Access tokens last 1 day and refresh tokens 30 days. Both, and the 30-day grace period for unused clients, are set under `mcp.oauth` in `config/tasknet.php`.

`php artisan tasknet:cleanup-oauth` purges old OAuth records and then deletes stale dynamically registered clients. It is not scheduled yet.

- Expired access-token rows are kept for the whole refresh-token lifetime. A refresh token only points at its access token, and that row is how TaskNet knows which workspace and client the refresh token belongs to.
- Only clients created through MCP dynamic registration are tracked in `mcp_dynamic_clients`. Clients registered before that table existed are never cleaned up automatically.
- A tracked client is deleted only when it is older than the grace period and no access token, authorization code or device code still references it. A client shared by several workspaces therefore stays until every workspace has lost its authorization.

## Realtime updates

Open dashboard and project pages update themselves when a project or task changes, whether the change came from the web UI, an MCP client or the Inspector. There is no polling.

```
web controller / MCP tool
  → shared action (CreateProject, UpdateTask, ...)
  → ProjectChanged / TaskChanged event (after the change is saved)
  → Laravel Reverb (WebSocket server)
  → Laravel Echo in the browser
  → debounced page reload
```

- Events are broadcast from the shared action layer, so every interface behaves the same. They are sent immediately (`ShouldBroadcastNow`), so no queue worker is needed.
- Each workspace has one private channel, `workspace.{id}`. `routes/channels.php` only authorizes the active owner of that workspace, and the payload carries nothing but the kind of change (`created`, `updated` or `deleted`). If Reverb is down, the change is still saved and the error is reported.
- Only the dashboard and project detail pages listen. Forms are never reloaded under you, and a reload waits while the tab is hidden or a dialog is open.
- The browser reaches Reverb on `localhost:8080`, while the Laravel containers reach it as `reverb:8080`. Both are set in `.env.example` (`REVERB_*` for the server side, `VITE_REVERB_*` for the browser).

If you created `.env` before realtime was added, copy the new `BROADCAST_CONNECTION`, `REVERB_*` and `VITE_REVERB_*` lines from `.env.example` into it, then run `docker compose up -d` (this starts the `reverb` service) and `docker compose restart node` so Vite picks up the new variables. A WebSocket server is not available on Vercel, so production realtime needs Reverb hosted elsewhere.

## Tests

The test suite uses in-memory SQLite (see `phpunit.xml`) and does not touch the Docker PostgreSQL database.

## Stopping

```bash
docker compose down        # Stop and remove containers; all data is kept
docker compose down -v     # Also delete the database, vendor and node_modules volumes
```

After `down -v`, `vendor` and `node_modules` are recreated on the next start, but the database is empty again.
