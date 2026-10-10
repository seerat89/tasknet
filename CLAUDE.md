# TaskNet

TaskNet is a Laravel portfolio project demonstrating how users can connect their own AI agents to an application through MCP and perform authorized operations.

Read and follow `AGENTS.md` before making changes. It contains the main Laravel and project engineering conventions.

## Current Direction

TaskNet is intended to be deployed publicly as a temporary MCP testing environment at https://tasknet.seerat.me (not deployed yet).

Demo workspaces are temporary and are not intended for real or sensitive project data.

Demo-account authentication (implemented; Fortify handles login/logout only):

- isolated workspace per demo user (the `User` model, `is_demo = true`)
- generated Demo ID and password, shown once (`App\Actions\CreateDemoWorkspace`)
- no real email, no email verification, no password recovery
- 30-day expiry, enforced at login and by middleware; expired workspaces are deleted by `tasknet:cleanup-demos` (scheduled daily)
- limits of 10 projects and 100 tasks per workspace are defined in `config/tasknet.php` but not enforced yet (Projects and Tasks do not exist)
- web UI and future MCP access must use the same authorization and limits

Infrastructure:

- local development: SQLite (Laravel through Herd on Windows)
- automated tests: in-memory SQLite (see `phpunit.xml`)
- production target: Neon Postgres, on Vercel at tasknet.seerat.me
- Vercel and Neon are targets only; nothing is deployed or configured yet

Database compatibility: development uses SQLite and production will use PostgreSQL, so prefer standard Laravel migrations and Eloquent. Avoid SQLite-specific raw SQL, and avoid PostgreSQL-specific features unless explicitly approved. Verify production migrations and important flows against Neon/PostgreSQL before launch.

## Development Rules

Never read, print, expose, or modify `.env`, credentials, private keys, auth tokens, or other secret files.

Use `.env.example` when environment-variable names are needed.

Do not commit or push unless explicitly asked.

Do not add dependencies without approval.

Use Laravel Boost and the installed Laravel documentation/tools when version-specific behavior needs verification.
