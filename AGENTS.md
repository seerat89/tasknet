<laravel-boost-guidelines>

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`.
- Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`.
- Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code.
- Do not create models without user approval, prefer tests with factories instead.
- Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion:
  `php artisan tinker --execute 'Your::code();'`
- Double quotes for PHP strings inside:
  `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`.
- Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters:
  `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments.
- Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- TaskNet's production deployment target is Vercel at https://tasknet.seerat.me, with Neon Postgres. It is not deployed yet.
- TaskNet does not target Laravel Cloud; do not apply Laravel Cloud guidance to this project.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files such as migrations, controllers, models, requests, policies, events, and tests.
- Use `php artisan list` and `php artisan [command] --help` when unsure about available options.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to Artisan commands executed by agents.
- Prefer framework-native patterns before introducing custom abstractions.

### Model Creation

- When creating new models, create useful factories and seeders for them too.
- Use `php artisan make:model --help` to confirm available generation options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless the existing application convention differs.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use model factories.
- Check whether a factory already exposes useful custom states before manually setting properties.
- Follow existing Faker conventions in the project.
- Create feature tests with `php artisan make:test --pest {name}`.
- Use unit tests only where a unit test is genuinely the appropriate boundary.
- Most application behavior should be covered by feature tests.

## Vite Error

- If you receive an `Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest` error, run `npm run build`, `npm run dev`, or `composer run dev` as appropriate.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If PHP files were modified, run:

  `vendor/bin/pint --dirty --format agent`

- Do not use Pint only as a check when formatting can be fixed automatically.
- Keep PHP formatting consistent with Laravel Pint.

=== pest/core rules ===

# Pest

- This project uses Pest.
- Create tests with:

  `php artisan make:test --pest {name}`

- Do not include the test suite directory in `{name}`.
- Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill when working substantially on tests.
- Do not delete tests or test files without approval.

## Running Tests

- Run the narrowest test set that covers the change:

  `php artisan test --compact path/to/test`

  or:

  `php artisan test --compact --filter=testName`

- Rerun a test after changing it.
- `vendor/bin/pest` may also be used directly.
- After relevant tests pass, run the complete suite before completing a feature:

  `php artisan test --compact`

=== tasknet rules ===

# TaskNet

TaskNet is a Laravel application whose web interface is a simple project and task manager.

Its primary technical purpose is to demonstrate how users can connect their own external AI agents to a Laravel application through the Model Context Protocol (MCP) and safely perform authorized operations.

Tagline:

> Taskboard for AI Agents

## Architecture

- The application must work fully without an AI agent.
- The normal web application uses server-rendered Blade views.
- Do not introduce React, Vue, Svelte, or Livewire unless explicitly approved.
- Tailwind CSS is the frontend styling system.
- Laravel Reverb and Echo may be used for realtime UI updates.
- MCP is an additional interface into the application, not a separate application architecture.
- Web controllers and MCP tools must reuse the same underlying application/business logic.
- Do not duplicate business rules between web controllers and MCP tools.
- Keep controllers thin.
- Keep MCP tools thin.
- Do not place substantial business logic directly in Blade views, controllers, routes, or MCP tools.
- Prefer Laravel-native patterns and abstractions over custom infrastructure.

## Domain

The primary application entities are:

- User
- Project
- Task

Relationships:

- A User has many Projects.
- A Project belongs to a User.
- A Project has many Tasks.
- A Task belongs to a Project.

Additional domain concepts should only be introduced when required by a real feature.

## Authentication

- Laravel Fortify handles the normal web authentication flow.
- Authentication UI is implemented with Blade.
- Current supported web authentication flows are:
  - Start demo (creates a demo workspace)
  - Demo login (Demo ID + password) and logout
- Registration, password reset, email verification, two-factor authentication and passkeys are intentionally disabled.
- Do not add authentication features that do not have a product requirement.

### Demo accounts

TaskNet is a public, temporary MCP demo. Demo workspaces are not for real or sensitive data.

- isolated workspace per demo user
- generated Demo ID and password, shown once
- no real email, no email verification, no password recovery
- 30-day expiry (`config/tasknet.php`)
- maximum 10 projects and 100 tasks per workspace, defined in `config/tasknet.php`; enforcement is not implemented yet
- the web UI and MCP must enforce the same authorization and limits

## Authorization

- Authorization must be enforced server-side.
- Use Laravel Policies for model authorization.
- Never rely on the UI or an AI agent to enforce permissions.
- MCP requests must be subject to the same authorization rules as normal application requests.
- An MCP client must never gain broader data access than the authenticated TaskNet user.
- Never bypass policies for convenience.

## MCP

- The Laravel MCP implementation is an interface to existing application capabilities.
- MCP tools must not contain duplicated business logic.
- MCP tools must validate input.
- MCP tools must authorize the authenticated user.
- MCP tools must return only the data required by the operation.
- Start with safe CRUD capabilities.
- Destructive operations and bulk operations must be added deliberately.
- Delete and bulk operations should receive additional review before being exposed through MCP.
- Do not expose internal implementation details, secrets, stack traces, or unrelated records through MCP responses.
- Use Laravel's official MCP package and current Laravel documentation.

## Realtime UI

- Realtime updates should be implemented using Laravel events, broadcasting, Reverb, and Echo where appropriate.
- The intended demo flow is:

  External AI agent
  → MCP tool
  → application logic
  → database
  → Laravel event
  → Reverb
  → Blade dashboard update

- Realtime behavior should enhance the UI without turning the application into a SPA.

## Database

- SQLite is the local development database.
- Automated tests currently use in-memory SQLite (`phpunit.xml`).
- The production target is Neon Postgres, deployed on Vercel at https://tasknet.seerat.me. Neither is set up yet.
- Because development uses SQLite and production will use PostgreSQL, prefer standard Laravel migrations and Eloquent. Avoid SQLite-specific raw SQL, and avoid PostgreSQL-specific features unless explicitly approved.
- Verify production migrations and important flows against Neon/PostgreSQL before launch.
- Use migrations for schema changes.
- Use foreign key constraints.
- Use appropriate indexes.
- Use Eloquent relationships rather than manually reproducing relationship logic.
- Prefer enums or well-defined value constraints for domain values such as task status and priority when appropriate.

## Validation

- Use Form Request classes for non-trivial HTTP validation.
- MCP inputs must have explicit schemas and server-side validation.
- Validation rules must live server-side.
- Never trust values coming from the browser, an MCP client, or an AI model.

## Frontend

- Use Blade.
- Use Tailwind CSS 4.
- Keep UI components reusable where repetition exists.
- Use semantic HTML.
- Form inputs must have labels.
- Maintain visible keyboard focus states.
- Validation errors must be clearly associated with their fields.
- Preserve accessibility when adding JavaScript behavior.
- Keep JavaScript minimal unless functionality requires it.
- Maintain TaskNet's visual direction:
  - clean
  - minimal
  - neutral backgrounds
  - teal/green accents
  - restrained use of color
  - professional dashboard appearance

## Tests

- New behavior must be tested.
- Prefer feature tests for application behavior.
- Test authorization boundaries.
- Test that users cannot access other users' projects or tasks.
- MCP tools must have tests covering:
  - successful operations
  - validation failures
  - unauthenticated access
  - unauthorized access
  - user data isolation
- Regression tests should be added for bugs.

## Code Quality

- Use explicit types where appropriate.
- Use descriptive class, method, and variable names.
- Avoid premature abstraction.
- Avoid service classes that merely wrap a single Eloquent call without adding useful behavior.
- Introduce services/actions only when they provide a meaningful application boundary or are reused across interfaces such as Blade and MCP.
- Prefer small focused classes.
- Prefer framework conventions over clever solutions.
- Keep dependency additions deliberate and minimal.

## Git

- Keep commits focused and understandable.
- Use Conventional Commit-style prefixes where appropriate:
  - `feat:`
  - `fix:`
  - `refactor:`
  - `test:`
  - `docs:`
  - `chore:`
  - `ci:`
- Do not commit generated secrets, local configuration, credentials, or machine-specific files.
- Do not commit on behalf of the user unless explicitly requested.
- Do not push changes unless explicitly requested.

## Secrets and Sensitive Files

Never read, inspect, print, modify, copy, summarize, or expose:

- `.env`
- `.env.*`
- `*.pem`
- `*.key`
- private keys
- API tokens
- passwords
- OAuth client secrets
- access tokens
- refresh tokens
- signing secrets
- production credentials

Use `.env.example` whenever configuration context is required.

Do not include secrets in:

- source code
- logs
- tests
- fixtures
- screenshots
- MCP responses
- Git commits
- documentation

If a task appears to require access to a secret, stop and ask the user for a safe alternative rather than reading the secret.

## Agent Workflow

Before implementing a feature:

1. Understand the requested behavior.
2. Inspect the relevant existing code.
3. Check applicable `.ai/rules` if present.
4. Use Laravel Boost documentation search for version-sensitive Laravel/package behavior.
5. Plan the smallest coherent implementation.
6. Use Artisan generators where appropriate.
7. Implement the feature.
8. Run the narrowest relevant tests.
9. Run Laravel Pint for modified PHP code.
10. Run the complete test suite when the feature is complete.
11. Review the diff for unnecessary changes.
12. Summarize what changed and identify anything requiring user review.

Do not make unrelated refactors while implementing a feature unless they are necessary for correctness.

</laravel-boost-guidelines>