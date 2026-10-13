# TaskNet agent guide

TaskNet is a Laravel project and task manager that demonstrates how users connect their own AI agents to an application through MCP. The browser UI and the MCP server are two interfaces over the same application logic. See `README.md` for the overview and `docs/development.md` for local setup.

## Runtime

- Docker Compose is the runtime (PHP 8.5, PostgreSQL, Node). Run Artisan, Composer, npm and tests through it, for example `docker compose exec app php artisan test`.
- Do not switch the project to Sail or WSL.
- `vendor/` and `node_modules/` live in Docker volumes; the host copies can be empty or stale.

## Architecture

- Server-rendered Blade with Tailwind CSS 4. Do not add React, Vue, Svelte or Livewire. Keep JavaScript minimal.
- Controllers, MCP tools and Blade views stay thin. Business rules live in `app/Actions`, policies and the limits in `config/tasknet.php`, and are shared by the web UI and MCP. Never duplicate a rule in a controller or tool.
- Realtime updates are broadcast from the shared actions through Reverb and Echo.
- Stick to the existing directory structure and conventions; check sibling files before adding new ones.

## Authorization and MCP

- Enforce authorization server-side with policies. Never bypass workspace isolation, and never accept `user_id`, `demo_id` or similar identity fields as MCP input.
- MCP tools must validate input, authorize the OAuth user, and return only what the operation needs.
- MCP delete and bulk tools are intentionally absent. Do not add them without explicit approval.
- Only the authorization-code (PKCE) and refresh-token OAuth grants are enabled.

## Data and privacy

- PostgreSQL is the development and production database. Tests run on in-memory SQLite locally (`phpunit.xml`) and on PostgreSQL in CI, so write standard Laravel migrations and Eloquent; avoid database-specific raw SQL.
- Do not persist raw IP addresses or User-Agent strings. The `sessions` table has no columns for them, and `App\Support\PrivacyDatabaseSessionHandler` keeps it that way. IPs may be used transiently, for example for rate limiting.
- Demo workspaces are temporary and not for sensitive data. Do not add accounts, email, tracking or consent storage.

## Secrets

- Never read, print, edit or commit `.env`, `.env.*`, keys, tokens or credentials. Use `.env.example` for variable names.
- Do not put secrets in code, tests, fixtures or docs.

## Working rules

- Add or update tests for behavior changes, then run the narrowest relevant tests first. Run the full suite, `./vendor/bin/pint --test` and `npm run build` when code changes are done, not for copy-only edits.
- Do not add or upgrade dependencies, or run `npm audit fix`, `composer update` or `boost:update`, without approval.
- Do not commit or push unless explicitly asked. Use Conventional Commit-style one-line messages.
- Do not create documentation files unless requested. Keep changes focused; avoid unrelated refactors.
