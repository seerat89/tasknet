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

## Tests

The test suite uses in-memory SQLite (see `phpunit.xml`) and does not touch the Docker PostgreSQL database.

## Stopping

```bash
docker compose down        # Stop and remove containers; all data is kept
docker compose down -v     # Also delete the database, vendor and node_modules volumes
```

After `down -v`, `vendor` and `node_modules` are recreated on the next start, but the database is empty again.
