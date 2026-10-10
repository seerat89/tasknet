<p align="center">
    <img src="public/brand/tasknet-logo-color.svg" width="260" alt="TaskNet">
</p>

<p align="center">
    <strong>Taskboard for AI Agents</strong>
</p>

# TaskNet

TaskNet is a small Laravel project/task app that demonstrates how users can connect their own AI agent through MCP and perform authorized operations.

It is planned as a temporary public MCP testing environment at https://tasknet.seerat.me (not deployed yet). It is not for real or sensitive project data.

## Stack

- Laravel 13, PHP 8.5, Blade, Tailwind CSS 4
- SQLite for local development and tests
- Pest
- Planned: Laravel MCP, Vercel and Neon Postgres for production

## Local Development

Runs locally with SQLite, for example through Herd.

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
composer run dev
```

## Tests

```bash
php artisan test
```

Status: in development.
