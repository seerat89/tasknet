# Development

## Requirements

Docker with the Compose plugin, and Git. PHP, PostgreSQL and Node run in containers, so nothing else needs to be installed. Keep the repository on your normal filesystem.

## Setup and run

```bash
git clone https://github.com/seerat89/tasknet.git
cd tasknet
cp .env.example .env        # PowerShell: Copy-Item .env.example .env
docker compose up -d --build
```

Open http://localhost:8000. The first start takes a minute or two (`docker compose logs -f`). On startup the `app` container installs Composer dependencies, generates `APP_KEY` and the OAuth keys if missing, runs migrations and serves the app. The `node` container installs npm packages when `package-lock.json` has changed, builds the MCP App and starts Vite.

| Service    | Purpose                   | Port on the host                         |
|------------|---------------------------|------------------------------------------|
| `app`      | PHP 8.5, `artisan serve`  | 8000                                     |
| `postgres` | PostgreSQL 18             | 5434 (`postgres:5432` inside Docker)     |
| `node`     | Node 24, Vite dev server  | 5173                                     |
| `reverb`   | Laravel Reverb WebSockets | 8080                                     |

Ports are published on the loopback addresses only, so other devices on your network cannot reach them. A tunnel running on your machine can.

`vendor/` and `node_modules/` live in Docker volumes, so run Composer, npm and Artisan through the containers:

```bash
docker compose exec app php artisan <command>
docker compose exec app composer <command>
docker compose exec node npm <command>
docker compose down        # stop; add -v to also delete the database and dependency volumes
```

After pulling new migrations, run `docker compose restart app` or `docker compose exec app php artisan migrate`.

To inspect the database, connect to `localhost:5434`, database `tasknet`, user `tasknet_app`, with `DB_PASSWORD` from your `.env` (a local-only password).

## Tests and checks

```bash
docker compose exec app php artisan test          # Pest, in-memory SQLite; never touches the dev database
docker compose exec app ./vendor/bin/pint         # format (add --test to only check)
docker compose exec node npm run typecheck        # TypeScript check of the MCP App
docker compose exec node npm test                 # Vitest tests of the MCP App
docker compose exec node npm run build            # production assets and the MCP App
docker compose exec app composer audit
docker compose exec node npm audit
```

Some backend tests read the built MCP App, so build it first (the `node` container does this on start).

GitHub Actions (`.github/workflows/ci.yml`) runs these checks with the backend tests on PostgreSQL. It fails on high-severity findings in production npm dependencies and only reports other npm findings. To run the suite on PostgreSQL locally, create a scratch database and run `docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=<scratch db> app php artisan test`.

## Realtime

Dashboard and project pages reload when a project or task changes, from the web UI or MCP:

```
action → ProjectChanged / TaskChanged → Reverb → Echo in the browser → debounced reload
```

Events are broadcast immediately, so no queue worker is needed. Each workspace has one private channel, `workspace.{id}`, and the payload only says what kind of change happened. The containers reach Reverb as `reverb:8080`; the browser reaches it at the host and port given by `VITE_REVERB_HOST`, `VITE_REVERB_PORT` and `VITE_REVERB_SCHEME`, which are read when a page is rendered (not at build time). If your `.env` predates realtime, copy the `REVERB_*` and `VITE_REVERB_*` lines from `.env.example` and run `docker compose up -d`.

## MCP

The MCP server is at http://localhost:8000/mcp (Streamable HTTP). The [README](../README.md#mcp-tools) lists its twelve tools: eight that read and change data, and four that open an interactive view. Tools run as the authorized user through the same actions, policies and limits as the web UI. There are no delete tools.

### OAuth

Clients sign users in through Laravel Passport:

1. The client reads `/.well-known/oauth-protected-resource/mcp` and `/.well-known/oauth-authorization-server`.
2. It registers at `POST /oauth/register` as a public client. Its callback address must be allowed by `MCP_REDIRECT_DOMAINS`; ChatGPT's two callback formats are always allowed, and listing `http://localhost` or `http://127.0.0.1` allows loopback callbacks on any port.
3. The user signs in with a Demo ID and approves at `/oauth/authorize` (authorization code with PKCE S256, scope `mcp:use`).
4. The client exchanges the code at `/oauth/token` for an access token (1 day) and a refresh token (30 days).

Signing keys are created in `storage/oauth-*.key` on first start and are git-ignored. Lifetimes are set under `mcp.oauth` in `config/tasknet.php`.

The public guide at `/connect` shows the server URL built from `APP_URL`, with client instructions. When a client changes its menus, update `resources/views/connect.blade.php`.

### Authorized clients

Workspace settings list the clients that hold a live access or refresh token. The `mcp_client_authorizations` table adds two dates for each workspace and client:

- `authorized_at` is set only when the user presses Approve. Token refreshes and Passport's silent re-approval do not change it.
- `last_used_at` is set after a successful tool call or resource read, at most once every `tasknet.mcp.activity_interval_minutes`.

Neither is backfilled; a missing value is shown as not recorded. Revoking access deletes the workspace's tokens, authorization codes and record for that client. The client registration is shared between workspaces and is left to the scheduled cleanup.

Passport checks a refresh token or authorization code and stores the new tokens in separate statements, so a revocation in between could be undone. `app/Support/OAuth` replaces two Passport repositories: after storing the new tokens it locks and re-reads the presented credential, and issues nothing if revocation has deleted it. `RevokeMcpClientAccess` relies on this and takes hold of those credentials before deleting tokens, so keep its order when changing either. The tests in `McpRevocationRaceTest.php` cover the ordering in one process; they do not exercise row locks.

### MCP App

`resources/mcp-app` holds the interactive view. `vite.mcp.config.ts` builds it into one file, `resources/dist/mcp-app/app.html` (git-ignored, and outside `public/` so it is served only through MCP). The Vite dev server does not rebuild it, so after editing run:

```bash
docker compose exec node npm run build:mcp
```

The `show_*` tools point clients to `ui://tasknet/app-<hash>.html`, where the hash comes from the built file. A new build therefore gets a new address without any manual version number, and a template resource serves the current page to clients that still hold an earlier address. After a change, refresh the connection in the client so it reads the new tool list.

The page runs in the client's sandbox with no network access of its own. It saves data only by calling the data tools through the client.

### MCP Inspector

```bash
npx @modelcontextprotocol/inspector --server-url http://localhost:8000/mcp --transport http
```

Connect, sign in with a demo workspace's Demo ID when redirected, and approve. The tools appear on the Tools tab.

### Remote AI clients (tunnels)

Browser-based and cloud AI clients cannot reach `localhost`. Expose port 8000 with a tunnel such as ngrok (`ngrok http 8000`) or Cloudflare Tunnel (`cloudflared tunnel --url http://localhost:8000`), then:

1. Set `APP_URL` in `.env` to the tunnel's `https://` address and run `docker compose restart app`.
2. Add the client's callback origin to `MCP_REDIRECT_DOMAINS` (comma-separated, keep the localhost entries) and restart the app. Claude needs `https://claude.ai`. Clients that use a custom URL scheme need it added to `custom_schemes` in `config/mcp.php`.
3. Check that `curl -i <tunnel>/mcp -X POST` returns `401` and `curl <tunnel>/.well-known/oauth-authorization-server` returns `https://` URLs.
4. Follow `<tunnel>/connect` and use `<tunnel>/mcp` as the server URL.

Troubleshooting: `invalid_redirect_uri` means the callback origin is missing from `MCP_REDIRECT_DOMAINS`; `429` is the per-workspace rate limit (`MCP_RATE_LIMIT`); `403` on `/mcp` means the workspace expired or the token lacks the `mcp:use` scope. If realtime updates do not arrive on a page opened through the tunnel, the browser is still being sent to `localhost` for Reverb.

A tunnel exposes your local instance to anyone with the address. Use throwaway data, stop the tunnel afterwards, and set `APP_URL` back to `http://localhost:8000`.

## Scheduled cleanup

`routes/console.php` schedules two daily jobs (UTC): `tasknet:cleanup-demos` at 02:00 deletes expired demo workspaces, and `tasknet:cleanup-oauth` at 02:30 purges old OAuth records and stale dynamically registered clients. The OAuth cleanup includes the Passport purge, so do not schedule `passport:purge` separately. Both are safe to rerun.

Locally, use `php artisan schedule:list`, `schedule:run` or `schedule:work`. In production there is no scheduler process: Vercel Cron calls `GET /cron/cleanup` daily at 02:00 UTC, which runs both commands in that order. It requires `Authorization: Bearer <CRON_SECRET>` (Vercel sends it once `CRON_SECRET` is set) and rejects every request while the secret is unset.

## Production

`Dockerfile.vercel`, `Caddyfile`, `docker/vercel-start.sh` and `vercel.json` describe the container Vercel runs (FrankenPHP on PHP 8.5). Local development does not use them. The image builds the frontend assets and the MCP App in its Node stage and copies `public/build` and `resources/dist` into the PHP image. To try it locally:

```bash
docker build -f Dockerfile.vercel -t tasknet-vercel .
docker run --rm -p 8081:80 -e APP_KEY="$(docker run --rm tasknet-vercel php artisan key:generate --show)" tasknet-vercel
```

Open http://localhost:8081/up. Pages that use sessions also need the `DB_*` variables and a migrated database. The image contains no `.env`, keys or credentials and never runs migrations.

Configuration comes from environment variables:

- **Application:** `APP_KEY`, and `APP_URL` set to the public `https://` address. The connection guide shows the server URL built from it.
- **MCP clients:** `MCP_REDIRECT_DOMAINS` lists the callback origins clients may register. Include `https://claude.ai` for Claude; ChatGPT's callbacks need no entry. `MCP_RATE_LIMIT` optionally changes the limit of 120 requests per minute per workspace.
- **Database:** the Neon integration's pooled connection as `DB_DATABASE_URL` (used when `DB_URL` is unset). The unpooled URL is only for migrations, which CI runs as the `PROD_DATABASE_URL` secret after the tests pass on `main`.
- **OAuth keys:** `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`.
- **Realtime:** with `BROADCAST_CONNECTION=reverb` the container also runs Reverb on loopback, and Caddy proxies `/app/*` to it. Set `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` and `REVERB_ALLOWED_ORIGINS`, and leave `VITE_REVERB_*` unset so the browser connects to the site's own address. Vercel pins each WebSocket to one instance, so also set `REVERB_SCALING_ENABLED=true` and a `rediss://` `REDIS_URL` for cross-instance delivery. With any other broadcast value, Reverb does not start and realtime is simply off.
- **Cleanup cron:** `CRON_SECRET`.
