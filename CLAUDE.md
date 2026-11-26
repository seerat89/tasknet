# TaskNet

Read and follow `AGENTS.md`; it holds the project rules. TaskNet is deployed at https://tasknet.seerat.me (Vercel with Neon PostgreSQL).

Claude-specific working rules:

- Inspect `git status` and the relevant code before changing anything. Never reset, restore or discard unrelated uncommitted work.
- Never read, print or modify `.env`, keys or other secrets (see `.claude/settings.json`). Use `.env.example` for variable names.
- Do not commit or push unless explicitly asked.
- Keep changes focused and respect TaskNet's architecture and privacy decisions: shared actions for web and MCP, no raw IP or User-Agent storage.
- Run only the checks the change needs; do not run the full suite or a build for copy-only edits.

## Dependency management

TaskNet prioritizes simplicity, maintainability, security, and minimal external dependencies.

- Prefer native language features, framework capabilities, browser APIs, and existing dependencies.
- Do not introduce dependencies merely for convenience or trivial functionality.
- Every new dependency must have a clear technical justification.
- Evaluate maintenance activity, security, licensing, bundle size, and transitive dependencies before adoption.
- Prefer established, well-maintained libraries for complex protocols and security-sensitive functionality over custom implementations.
- Minimize coupling to third-party libraries and isolate replaceable integrations where practical.
- Do not introduce overlapping libraries that solve the same problem.
- Remove unused dependencies.
- Report all newly introduced direct dependencies and justify their necessity after implementation.
- Do not introduce additional frameworks or significant architectural dependencies without explicit approval.
