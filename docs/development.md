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

Open http://localhost:8000. The first start takes a minute or two (`docker compose logs -f`). On startup the `app` container installs Composer dependencies, generates `APP_KEY` and the OAuth keys if missing, runs migrations and serves the app. The `node` container installs npm packages and starts Vite.

| Service    | Purpose                   | Port                                        |
|------------|---------------------------|---------------------------------------------|
| `app`      | PHP 8.5, `artisan serve`  | 8000                                        |
| `postgres` | PostgreSQL 18             | 5434 on the host, `postgres:5432` in Docker |
| `node`     | Node 24, Vite dev server  | 5173                                        |
| `reverb`   | Laravel Reverb WebSockets | 8080                                        |

`vendor/` and `node_modules/` live in Docker volumes, so run Composer, npm and Artisan through the containers:

```bash
docker compose exec app php artisan <command>
docker compose exec app composer <command>
docker compose exec node npm <command>
docker compose down        # stop; add -v to also delete the database and dependency volumes
```

To inspect the database, connect to `localhost:5434`, database `tasknet`, user `tasknet_app`, with `DB_PASSWORD` from your `.env` (a local-only password).

## Tests and checks

```bash
docker compose exec app php artisan test     # Pest, in-memory SQLite; never touches the dev database
docker compose exec app ./vendor/bin/pint    # format (add --test to only check)
docker compose exec node npm run build       # production frontend build
```

GitHub Actions (`.github/workflows/ci.yml`) runs the same checks with the tests on PostgreSQL, plus dependency audits. To run the suite on PostgreSQL locally, create a scratch database and run `docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=<scratch db> app php artisan test`.

## Realtime

Dashboard and project pages reload when a project or task changes, from the web UI or MCP:

```
action → ProjectChanged / TaskChanged → Reverb → Echo in the browser → debounced reload
```

Events are broadcast immediately, so no queue worker is needed. Each workspace has one private channel, `workspace.{id}`, and the payload only says what kind of change happened. The containers reach Reverb as `reverb:8080`; the browser reaches it at the host and port given by `VITE_REVERB_HOST`, `VITE_REVERB_PORT` and `VITE_REVERB_SCHEME`, which are read when a page is rendered (not at build time). If your `.env` predates realtime, copy the `REVERB_*` and `VITE_REVERB_*` lines from `.env.example` and run `docker compose up -d`.

## MCP

The MCP server is at http://localhost:8000/mcp and authenticates with OAuth through Laravel Passport: the client registers at `POST /oauth/register` as a public client, the user signs in with a Demo ID and approves at `/oauth/authorize` (authorization code with PKCE, scope `mcp:use`), and the client exchanges the code at `/oauth/token` for access and refresh tokens.

The eight tools (`list_projects`, `get_project`, `create_project`, `update_project`, `list_tasks`, `get_task`, `create_task`, `update_task`) run as the authorized user through the same actions, policies and limits as the web UI. There are no delete tools.

OAuth signing keys are created in `storage/oauth-*.key` on first start and are git-ignored. Token and client lifetimes are set under `mcp.oauth` in `config/tasknet.php`.

### MCP Inspector

```bash
npx @modelcontextprotocol/inspector --server-url http://localhost:8000/mcp --transport http
```

Connect, sign in with a demo workspace's Demo ID when redirected, and approve. The eight tools appear on the Tools tab.

### Remote AI clients (tunnels)

Browser-based and cloud AI clients cannot reach `localhost`. Expose port 8000 with a tunnel such as ngrok (`ngrok http 8000`) or Cloudflare Tunnel (`cloudflared tunnel --url http://localhost:8000`), then:

1. Set `APP_URL` in `.env` to the tunnel's `https://` address and run `docker compose restart app`.
2. Add the client's callback origin to `MCP_REDIRECT_DOMAINS` (comma-separated, keep the localhost entries) and restart the app. Clients that use a custom URL scheme need it added to `custom_schemes` in `config/mcp.php`.
3. Check that `curl -i <tunnel>/mcp` returns `401` and `curl <tunnel>/.well-known/oauth-authorization-server` returns `https://` URLs.
4. Use `<tunnel>/mcp` as the server URL in the client.

Troubleshooting: `invalid_redirect_uri` means the callback origin is missing from `MCP_REDIRECT_DOMAINS`; `429` is the per-workspace rate limit (`MCP_RATE_LIMIT`); `403` on `/mcp` means the workspace expired or the token lacks the `mcp:use` scope.

A tunnel exposes your local instance to anyone with the address. Use throwaway data, stop the tunnel afterwards, and set `APP_URL` back to `http://localhost:8000`.

## Scheduled cleanup

`routes/console.php` schedules two daily jobs (UTC): `tasknet:cleanup-demos` at 02:00 deletes expired demo workspaces, and `tasknet:cleanup-oauth` at 02:30 purges old OAuth records and stale dynamically registered clients. The OAuth cleanup includes the Passport purge, so do not schedule `passport:purge` separately. Both are safe to rerun.

Locally, use `php artisan schedule:list`, `schedule:run` or `schedule:work`. In production there is no scheduler process: Vercel Cron calls `GET /cron/cleanup` daily at 02:00 UTC, which runs both commands in that order. It requires `Authorization: Bearer <CRON_SECRET>` (Vercel sends it once `CRON_SECRET` is set) and rejects every request while the secret is unset.

## Production image

`Dockerfile.vercel`, `Caddyfile`, `docker/vercel-start.sh` and `vercel.json` describe the container Vercel runs (FrankenPHP on PHP 8.5). Local development does not use them. To try the image locally:

```bash
docker build -f Dockerfile.vercel -t tasknet-vercel .
docker run --rm -p 8081:80 -e APP_KEY="$(docker run --rm tasknet-vercel php artisan key:generate --show)" tasknet-vercel
```

Open http://localhost:8081/up. Pages that use sessions also need the `DB_*` variables and a migrated database. The image contains no `.env`, keys or credentials and never runs migrations.

Configuration comes from environment variables:

- **Database:** the Neon integration's pooled connection as `DB_DATABASE_URL` (used when `DB_URL` is unset). The unpooled URL is only for migrations, which CI runs as the `PROD_DATABASE_URL` secret after the tests pass on `main`.
- **OAuth keys:** `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`.
- **Realtime:** with `BROADCAST_CONNECTION=reverb` the container also runs Reverb on loopback, and Caddy proxies `/app/*` to it. Set `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` and `REVERB_ALLOWED_ORIGINS`, and leave `VITE_REVERB_*` unset so the browser connects to the site's own address. Vercel pins each WebSocket to one instance, so also set `REVERB_SCALING_ENABLED=true` and a `rediss://` `REDIS_URL` for cross-instance delivery. With any other broadcast value, Reverb does not start and realtime is simply off.
- **Cleanup cron:** `CRON_SECRET`.
