# TaskNet

Read and follow `AGENTS.md`; it holds the project rules. TaskNet is not deployed yet (target: https://tasknet.seerat.me on Vercel with Neon PostgreSQL).

Claude-specific working rules:

- Inspect `git status` and the relevant code before changing anything. Never reset, restore or discard unrelated uncommitted work.
- Never read, print or modify `.env`, keys or other secrets (see `.claude/settings.json`). Use `.env.example` for variable names.
- Do not commit or push unless explicitly asked.
- Keep changes focused and respect TaskNet's architecture and privacy decisions: shared actions for web and MCP, no raw IP or User-Agent storage.
- Run only the checks the change needs; do not run the full suite or a build for copy-only edits.
