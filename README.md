<p align="center">
    <img src="public/brand/tasknet-logo-color.svg" width="260" alt="TaskNet">
</p>

<p align="center">
    <strong>Taskboard for AI Agents</strong>
</p>

# TaskNet

TaskNet is a small Laravel project/task app that demonstrates how users can connect their own AI agent through MCP and perform authorized operations.

It is planned as a temporary public MCP testing environment at https://tasknet.seerat.me (not deployed yet). It is not for real or sensitive project data.

## Demo Workspaces

- Each visitor gets an isolated, temporary demo workspace. There is no registration, email address or password recovery.
- Credentials are a generated Demo ID (e.g. `TN-7K4P2Q`) and password, shown once. TaskNet stores only a password hash.
- A workspace expires 30 days after creation and is then deleted (`php artisan tasknet:cleanup-demos`, scheduled daily).
- Planned workspace limits: 10 projects and 100 tasks (see `config/tasknet.php`).
- TaskNet is for MCP testing, not real or sensitive work.

## Stack

- Laravel 13, PHP 8.5, Blade, Tailwind CSS 4
- PostgreSQL for local development (Docker); in-memory SQLite for the test suite
- Pest
- Planned: Laravel MCP, Vercel and Neon Postgres for production

## Local development with Docker

Docker provides reproducible local PHP 8.5, PostgreSQL 18 and Node 24 versions for the whole team, so nothing but Docker needs to be installed. Production uses the same technology family (PHP and PostgreSQL) on Vercel with Neon Postgres. Keep the repository on your normal filesystem; Docker is only the runtime.

Requirements: Docker Desktop or Docker Engine with the Compose plugin.

```bash
# 1. Clone
git clone https://github.com/<your-account>/tasknet.git
cd tasknet

# 2. Environment file (Docker defaults are already set)
cp .env.example .env        # Windows PowerShell: Copy-Item .env.example .env

# 3. Build and start everything
docker compose up -d --build
```

Open http://localhost:8000 (give the first start a minute or two while dependencies install; follow progress with `docker compose logs -f`). Vite runs on port 5173.

What `docker compose up` does, in order (see `compose.yaml`):

1. `postgres` starts; `app` waits until its healthcheck passes.
2. `app` runs `composer install` if `vendor/autoload.php` is missing.
3. `app` runs `php artisan key:generate` if `APP_KEY` in `.env` is empty.
4. `app` runs `php artisan migrate --force`, then starts `php artisan serve`.
5. `node` independently runs `npm install` if `node_modules` is empty, then starts Vite.

Every step is skipped when already done, so later starts are fast. After changing `composer.json` or `package.json`, run `docker compose run --rm app composer install` or `docker compose run --rm node npm install`.

Services: `app` (PHP `artisan serve`, port 8000), `postgres` (database `tasknet`, reachable inside the Compose network as `postgres:5432`, data kept in the `postgres-data` volume) and `node` (Vite dev server). `vendor/` and `node_modules/` live in Docker volumes, so they are fast and Linux-native packages never mix with your host checkout. This also means those folders on your host stay empty; use the containers for Composer, npm and tests.

Optional host database tools (DBeaver, pgAdmin) can connect with Host `localhost`, Port `5434`, Database `tasknet`, Username `tasknet_app` and the `DB_PASSWORD` from your `.env`. Laravel inside Docker keeps using `DB_HOST=postgres` and `DB_PORT=5432`.

### Everyday commands

```bash
docker compose run --rm app php artisan <command>     # Artisan
docker compose run --rm app composer <command>        # Composer
docker compose run --rm node npm <command>            # npm, e.g. npm run build
docker compose run --rm app php artisan test          # Test suite
docker compose logs -f                                # Follow logs
docker compose down                                   # Stop (data is kept)
```

`docker compose down -v` also deletes the database, `vendor` and `node_modules` volumes (they are recreated on the next start, but the database is empty again).

The `.env` file holds a local-only development password. Never reuse it elsewhere or commit `.env`.

## Tests

The test suite uses in-memory SQLite (see `phpunit.xml`) and does not touch the Docker PostgreSQL database:

```bash
docker compose run --rm app php artisan test
```

Status: in development.
