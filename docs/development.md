# Development

## Requirements

- Docker with the Compose plugin
- Git

PHP, PostgreSQL and Node run in containers, so nothing else needs to be installed. Keep the repository on your normal filesystem.

## Start the application

```bash
git clone https://github.com/seerat89/tasknet.git
cd tasknet
cp .env.example .env        # PowerShell: Copy-Item .env.example .env
docker compose up -d --build
```

Open http://localhost:8000. The first start takes a minute or two; follow it with `docker compose logs -f`.

On startup the `app` container installs Composer dependencies if needed, generates `APP_KEY` if it is empty, runs migrations and serves the app. The `node` container installs npm packages if needed and starts Vite.

## Services

| Service    | Purpose                    | Port                                         |
|------------|----------------------------|----------------------------------------------|
| `app`      | PHP 8.5, `artisan serve`   | 8000                                         |
| `postgres` | PostgreSQL 18              | 5434 on the host, `postgres:5432` in Docker  |
| `node`     | Node 24, Vite dev server   | 5173                                         |
| `reverb`   | Laravel Reverb WebSockets  | 8080                                         |

`vendor/` and `node_modules/` live in Docker volumes, so the folders in your checkout stay empty or stale. Run Composer, npm and tests through the containers.

## Database

Migrations run automatically on startup; run them manually with `docker compose exec app php artisan migrate`.

To inspect the database with DBeaver, pgAdmin or similar, connect to host `localhost`, port `5434`, database `tasknet`, user `tasknet_app`, and the `DB_PASSWORD` from your `.env`. That password is for local development only.

TaskNet does not persist IP addresses or User-Agent strings in application session records.

## Tests and formatting

```bash
docker compose exec app php artisan test       # Pest; in-memory SQLite, never touches the dev database
docker compose exec app ./vendor/bin/pint      # format (add --test to only check)
docker compose exec node npm run build         # production frontend build
```

GitHub Actions (`.github/workflows/ci.yml`) runs the same checks with the tests on PostgreSQL. To run the suite against PostgreSQL locally, create a scratch database and run `docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=<scratch db> app php artisan test`.

## Realtime updates

Dashboard and project pages update when a project or task changes, whether through the web UI or MCP:

```
action → ProjectChanged / TaskChanged event → Reverb → Echo in the browser → debounced reload
```

Events are sent immediately (no queue worker). Each workspace has one private channel, `workspace.{id}`, and the payload only says what kind of change happened. The browser reaches Reverb at `localhost:8080`; the containers reach it as `reverb:8080`. Both are configured in `.env.example` (`REVERB_*`, `VITE_REVERB_*`). If your `.env` predates realtime, copy those lines over and run `docker compose up -d` and `docker compose restart node`.

## MCP development

The MCP server is at http://localhost:8000/mcp. Clients authenticate with OAuth through Laravel Passport:

1. `/mcp` answers `401` with a `WWW-Authenticate` header pointing at the OAuth metadata.
2. The client registers itself at `POST /oauth/register` as a public client.
3. The user signs in with a Demo ID and approves the connection at `/oauth/authorize` (authorization code with PKCE, scope `mcp:use`).
4. The client exchanges the code at `/oauth/token` for an access token and a refresh token.

Tools: `list_projects`, `get_project`, `create_project`, `update_project`, `list_tasks`, `get_task`, `create_task` and `update_task`, all advertised directly. They run as the authorized user through the same actions, policies and limits as the web UI. There are no delete tools.

OAuth signing keys are created in `storage/oauth-*.key` on first start and are git-ignored. For hosted environments, set `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` instead.

### MCP Inspector

```bash
npx @modelcontextprotocol/inspector --server-url http://localhost:8000/mcp --transport http
```

Connect, sign in with a demo workspace's Demo ID when redirected, and approve. The eight tools appear on the Tools tab; pick one, enter its arguments and run it.

### Remote AI clients (tunnels)

Browser-based and cloud AI clients cannot reach `localhost`. Expose port 8000 with a tunnel such as ngrok (`ngrok http 8000`) or Cloudflare Tunnel (`cloudflared tunnel --url http://localhost:8000`), then:

1. Set `APP_URL` in `.env` to the tunnel's `https://` address and run `docker compose restart app`.
2. Add the client's callback origin to `MCP_REDIRECT_DOMAINS` (comma-separated, keep the localhost entries) and restart the app. Clients that use a custom URL scheme need it added to `custom_schemes` in `config/mcp.php`.
3. Check `curl -i <tunnel>/mcp` returns `401` and `curl <tunnel>/.well-known/oauth-authorization-server` returns URLs starting with `https://`.
4. Use `<tunnel>/mcp` as the server URL in the client.

Troubleshooting:

- `invalid_redirect_uri` on registration: the callback origin is missing from `MCP_REDIRECT_DOMAINS`.
- `http://` URLs in the OAuth metadata: trusted proxies are not configured, so HTTPS may not be detected behind a tunnel.
- `429`: the per-workspace MCP rate limit (`MCP_RATE_LIMIT`) was reached.
- `403` on `/mcp`: the workspace expired or the token lacks the `mcp:use` scope.

A tunnel exposes your local instance to anyone with the address. Set `APP_DEBUG=false`, use throwaway data, and stop the tunnel afterwards, then set `APP_URL` back to `http://localhost:8000`. MCP support for custom servers varies by client and plan.

### OAuth lifetimes

Access tokens last 1 day and refresh tokens 30 days. These values, and the 30-day grace period for unused clients, are set under `mcp.oauth` in `config/tasknet.php`. Expired access-token rows are kept until the refresh token's lifetime has passed, because the refresh token only identifies its workspace and client through that row.

## Scheduled cleanup

`routes/console.php` schedules two daily jobs in the application timezone (UTC):

- `tasknet:cleanup-demos` at 02:00 deletes expired demo workspaces.
- `tasknet:cleanup-oauth` at 02:30 purges old OAuth records, then deletes stale dynamically registered clients (tracked in `mcp_dynamic_clients`) that are past the grace period and have no remaining tokens or authorization codes. It includes the Passport purge, so do not schedule `passport:purge` separately.

Defining a schedule does not run it: production must invoke Laravel's scheduler, for example `php artisan schedule:run` every minute. Both commands are safe to rerun. Locally, use `php artisan schedule:list`, `schedule:run` or `schedule:work`.

## Useful commands

```bash
docker compose exec app php artisan <command>   # Artisan
docker compose exec app composer <command>      # Composer
docker compose exec node npm <command>          # npm
docker compose logs -f                          # follow logs
docker compose down                             # stop; data is kept
docker compose down -v                          # also delete the database and dependency volumes
```
