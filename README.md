<p align="center">
    <img src="public/brand/tasknet-logo-color.svg" width="260" alt="TaskNet">
</p>

<p align="center">
    <strong>Taskboard for AI Agents</strong>
</p>

# TaskNet

TaskNet is a small Laravel project and task manager that demonstrates how users can connect their own AI agent through MCP and perform authorized operations.

It is planned as a temporary public MCP testing environment at https://tasknet.seerat.me (not deployed yet). Demo workspaces are for testing only, not real or sensitive data.

## What it demonstrates

- A normal server-rendered Blade app for projects and tasks that works fully without an AI agent
- Isolated, temporary demo workspaces with generated credentials, no registration and 30-day expiry
- Server-side authorization, so an agent never gains broader access than its user
- One shared set of application logic behind both the web UI and MCP tools

## Stack

- Laravel 13, PHP 8.5, Blade, Tailwind CSS 4
- PostgreSQL 18 for local development; in-memory SQLite for tests
- Pest
- Docker Compose for reproducible development
- Laravel MCP + Laravel Passport (OAuth) for AI clients
- Production target: Vercel + Neon PostgreSQL

## Quick start

Requires Docker Desktop or Docker Engine.

```bash
git clone https://github.com/seerat89/tasknet.git
cd tasknet
cp .env.example .env
docker compose up -d --build
```

Open http://localhost:8000

The first start installs dependencies and runs migrations automatically. See [docs/development.md](docs/development.md) for the full development setup.

## MCP

AI clients connect to `/mcp` (locally http://localhost:8000/mcp) using OAuth. You approve the connection on a TaskNet screen while signed in with your Demo ID, so the AI client never receives your password. MCP tools run with the same permissions, limits and business rules as the web UI.

Tools: `list_projects`, `get_project`, `create_project`, `update_project`, `list_tasks`, `get_task`, `create_task`, `update_task`. There are no delete tools yet.

## Architecture

Web controllers and MCP tools are thin interfaces over the same application actions, policies and limits. Docker provides reproducible local PHP, PostgreSQL and Node versions; production uses the same PHP and PostgreSQL family on Vercel with Neon.

## Tests

```bash
docker compose run --rm app php artisan test
```

## Status

In development.
