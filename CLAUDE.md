# CLAUDE.md — laravel-prototype

Agentic project instructions for Claude Code. Read this file before making any changes.

---

## Design Philosophy

**Actions are named business operations. DTOs are their data contracts.**

Both are written to be readable by Product Owners and AI agents. `app/{Domain}/Actions/` is a product catalogue — each class name maps directly to a business requirement. DTOs make the data contract explicit without reading implementation code.

**Grow as you go.** Start with an Action and a DTO. Add a Service class only when an Action becomes too complex. Add repositories, events, and query objects only when the need is real — not in anticipation of it.

---

## Project Purpose

A Laravel 13 prototype evolving through defined phases. **Phase 8 (complete) — no active phase; see BUILD.md for history.** See `docs/agentic/BUILD.md` for full history and current architecture context.

Long-term vision: team starter template and migration target for a legacy PHP application.

---

## Conventions for Agentic Work

- **Before any change:** Read `docs/agentic/BUILD.md` for current architecture context
- **After any significant change:** Update `docs/agentic/BUILD.md` to reflect current state (git tracks history — no need to append dated entries)
- **All PHP commands:** Run via `docker compose exec app` (no native PHP on host)
- **New specs/plans:** Save to `.omc/specs/` and `.omc/plans/` respectively
- **Domain CLAUDE.md:** Every domain directory under `app/` gets a `CLAUDE.md` documenting: purpose, consumers, how to extend, auth model, and any non-obvious patterns
- **No AI attribution:** Never include `🤖 Generated with Claude Code` or any similar attribution text in git commit messages, PR descriptions, or GitHub issues
- **GitHub workflow:** Use `/issue` to create a GitHub issue and feature branch from a plan, and `/ship` to validate, commit, push, and open a PR targeting `develop`
- **Never commit directly to `develop`:** Always create a new branch first, commit there, push, and open a PR targeting `develop`
- **OpenAPI annotations:** Operation docs (`OA\Get`, `OA\Post`, etc.) belong on the Action class. Schema docs (`OA\Schema`, `OA\Property`) belong on the DTO class. Do not create `app/OpenApi/Schemas/` files for domain schemas. `storage/api-docs/openapi.yaml` is a generated artifact — never hand-edit it.
- **VERSION file:** `VERSION` (project root) is the single source of truth for `APP_VERSION` and `API_VERSION`. Do not add version numbers to `.env`.
- **Keep this file current:** After completing a phase, adding a convention, or changing how the project is built or run — update CLAUDE.md before ending the session

---

## Validation Gate

Run `/validate` before marking any implementation task complete. All five gates must pass:

```bash
docker compose exec app php artisan test --coverage --min=100
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=-1
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan l5-swagger:generate
docker compose exec app php artisan l5-swagger:audit
```

---

## Development Methodology

Vertical slice approach: implement one complete feature at a time, from route to test.

TDD loop: **RED** → write a failing test. **GREEN** → minimum code to pass. **REFACTOR** → clean up, run `/validate`, move on only when all gates pass.

---

## Laravel Boost MCP

Configured in `mcp.json` — Claude Code loads it automatically. Use these tools instead of manual alternatives:

- `search-docs` — version-specific Laravel/package documentation (always run before coding)
- `database-query` — read-only DB queries without tinker
- `database-schema` — inspect table structure before writing migrations or models
- `get-absolute-url` — resolve correct URL before sharing with user
- `browser-logs` — read browser errors and exceptions

---

_Last updated: 2026-08-06 (Trimmed CLAUDE.md — removed stack, commands, directory structure; deferred PHP/Pint/PHPUnit conventions to Laravel Boost)_
