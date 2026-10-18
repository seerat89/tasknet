<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="public/brand/tasknet-logo-color-on-dark.svg">
        <img src="public/brand/tasknet-logo-color.svg" width="260" alt="TaskNet">
    </picture>
</p>

<p align="center">
    <strong>Taskboard for AI Agents</strong>
</p>

# TaskNet

**Live demo: https://tasknet.seerat.me**

TaskNet is a Laravel task manager with an MCP server that lets AI clients securely manage projects and tasks through the same authorization, validation and business logic as the web application.

It demonstrates how an existing application can expose controlled capabilities to AI agents without creating a separate implementation of its business rules.

> **This is a demo, not a product.** Do not store sensitive, confidential or important information in it. Demo workspaces are temporary and are deleted automatically.

## Try it

1. Open https://tasknet.seerat.me and start a demo. TaskNet generates a Demo ID and password without requiring an email address. Save them: the password is shown once.
2. Create a few projects and tasks in the browser.
3. Add `https://tasknet.seerat.me/mcp` as a custom MCP server in your AI client. You sign in with your Demo ID on a TaskNet screen and approve the connection, so the client never sees your password.
4. Ask the agent to list, create or update projects and tasks. Keep the browser open: it updates on its own.

MCP support for custom servers varies by client and plan.

## What it demonstrates

- **One set of rules for two interfaces.** The web UI and the MCP tools call the same Laravel actions, policies, validation and limits. Nothing is reimplemented for the agent.
- **A real MCP server.** Built with Laravel MCP, with a deliberately small surface: eight tools, no delete tools.
- **OAuth 2.0 for AI clients.** Laravel Passport with the authorization-code flow, PKCE, refresh tokens and dynamic client registration. Users can revoke an authorized client from their workspace.
- **Realtime updates.** Browser and MCP changes are broadcast through Laravel Reverb and Echo, with Redis so it works across server instances.
- **Isolated, temporary workspaces.** Each demo has its own data, limits of 10 projects and 100 tasks, and expires after 30 days. A scheduled cleanup removes expired workspaces and stale OAuth records.
- **Privacy-minded by design.** No email, no analytics cookies, and no IP addresses or User-Agent strings stored in sessions.

## How it works

![TaskNet architecture: the browser (through the Laravel UI) and an AI client (through OAuth and the MCP server) both reach the same shared Laravel layer of actions, policies, validation and limits, which uses PostgreSQL. Events from that layer reach the browser through Reverb and Redis, and the browser refreshes automatically.](public/brand/architecture.svg)

An AI client connects to `/mcp` and authorizes through OAuth. Every tool call then runs as that workspace's user, so the same policies that protect the web UI protect the agent. After each change, the shared action emits an event; Reverb pushes it to the open browser, which refreshes. A realtime outage never fails a saved change.

## MCP tools

`list_projects` · `get_project` · `create_project` · `update_project` · `list_tasks` · `get_task` · `create_task` · `update_task`

All eight are advertised directly. There are intentionally no delete tools, so an agent can create and edit but not remove data.

## Architecture and stack

- **Application:** Laravel 13 on PHP 8.5, server-rendered Blade with Tailwind CSS 4 and minimal JavaScript
- **Data:** PostgreSQL (Neon in production), database-backed sessions
- **AI integration:** Laravel MCP and Laravel Passport
- **Realtime:** Laravel Reverb and Echo, with Upstash Redis for cross-instance delivery
- **Hosting:** Vercel container deployment (FrankenPHP); scheduled cleanup through Vercel Cron; Vercel Web Analytics
- **CI/CD:** GitHub Actions runs Pest tests on PostgreSQL, Laravel Pint, dependency audits and the frontend build, then migrates the production database. Vercel only releases to production after that migration check passes.
- **Local development:** Docker Compose

## Running locally

Requires Docker.

```bash
git clone https://github.com/seerat89/tasknet.git
cd tasknet
cp .env.example .env
docker compose up -d --build
```

Open http://localhost:8000. The first start installs dependencies and runs the migrations. Full setup, MCP testing and the production image are covered in [docs/development.md](docs/development.md).

```bash
docker compose exec app php artisan test
```

## Limits and caveats

- Demo workspaces expire after 30 days and are then deleted. Each is limited to 10 projects and 100 tasks.
- Demo access uses only the generated Demo ID and password. There is no email registration or password recovery, so keep your credentials.
- This is a portfolio/demo service and availability is not guaranteed. Realtime connections may reconnect periodically.
- Do not store sensitive, confidential or important information. See the privacy notice in the app for what TaskNet processes.
