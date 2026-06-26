# Phase 7 Plan — Developer Tooling

**Created:** 2026-06-26
**Status:** Scoping

## Objective

Codify the development workflow and improve OpenAPI tooling so every developer — regardless of whether they use OMC — has consistent, discoverable commands and a validated spec pipeline.

Four workstreams:

1. **`l5-swagger:audit`** — Artisan command to detect OpenAPI coverage gaps (CI-safe)
2. **Claude commands** — `.claude/commands/` files covering the full development lifecycle
3. **Contract testing** — Spectator integration to validate live responses against `openapi.yaml`
4. **CLAUDE.md trim** — replace verbose workflow blocks with command references; add Development Methodology section

---

## Pre-flight

Before writing any PHP, run `search-docs` via the Laravel Boost MCP for each workstream:

```
search-docs: ['artisan commands', 'console commands', 'make:command']
search-docs: ['testing http', 'feature tests', 'http tests']
search-docs: ['openapi', 'spectator', 'contract testing']
search-docs: ['database seeder', 'factories', 'sanctum tokens']
```

Do not skip this step — docs are version-specific and Laravel 13 / Sanctum 4 have breaking changes from earlier versions.

---

## Principles

1. **Commands over documentation** — workflows live in `.claude/commands/`, not CLAUDE.md prose
2. **Vertical slice development** — each plan step is a complete increment (route → action → service → test); no horizontal layer passes
3. **TDD per slice** — Feature test first (business contract), then unit tests for components; confirm failure before implementing
4. **Feature tests = business perspective; unit tests = component perspective** — Feature tests cover public API behaviour end-to-end via Laravel's test client; unit tests cover isolated components with mocks
5. **Contract tests = spec accuracy** — Spectator validates that live responses match declared OpenAPI schemas; distinct from Feature tests which only check behaviour
6. **CI-safe gates** — `l5-swagger:audit` exits non-zero on gaps; all gates must pass before `/ship`

---

## Workstream 1 — `l5-swagger:audit` Artisan Command

**File:** `app/Console/Commands/AuditOpenApiSpec.php`
**Signature:** `l5-swagger:audit {--fail-on-warnings}`

Compares `php artisan route:list` (API routes only) against the generated `storage/api-docs/openapi.yaml`.

Reports three categories:

| Category | Description |
|----------|-------------|
| **Undocumented** | Routes with no matching OA path |
| **Phantom** | OA paths with no matching route |
| **Incomplete** | Documented but missing: `operationId`, 401 on `auth:sanctum` routes, empty response schemas |

- Exits 0 if no undocumented or phantom paths (incomplete is a warning unless `--fail-on-warnings`)
- Output as a formatted table; CI-friendly single-line summary at the end

---

## Workstream 2 — Claude Commands

All stored in `.claude/commands/`. No OMC dependency — works with any Claude Code installation.

### `/issue`

Generates a GitHub issue and branch from an `.omc/plans/` plan file.

1. List available plans in `.omc/plans/` for selection (or accept path as argument)
2. Extract title, objective, and scope from the plan
3. Prompt for branch type: `feature/`, `fix/`, `chore/`, `docs/`, `refactor/`
4. Create GitHub issue from plan content
5. Create branch `{type}/{slug}` referencing the issue number and switch to it

### `/test`

Runs `php artisan test` intelligently:

- Default: filter to tests related to files changed since last commit
- `--all`: full suite
- `--coverage`: full suite with coverage report
- Always runs inside Docker: `docker compose exec app php artisan test`

### `/validate`

Runs the full 3-gate check in sequence:

```bash
docker compose exec app php artisan test --coverage --compact
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=-1
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan l5-swagger:generate
docker compose exec app php artisan l5-swagger:audit
```

Stops and reports on first failure. All gates must be green before `/ship`.

### `/ship`

Finalises a piece of work:

1. Runs `/validate` — stops if any gate fails
2. Stages relevant files (prompts for review)
3. Commits with a message referencing the open issue number
4. Pushes branch
5. Opens PR targeting `develop` with title and body derived from the issue

### `/openapi-audit`

Runs `php artisan l5-swagger:audit`, interprets the gap report, and suggests specific fixes for each finding (missing annotation, wrong response code, etc.).

### `/openapi-draft`

Given a PHP Action file path, generates draft `#[OA\...]` attribute blocks:

- Reads the Action's `asController()` signature, route binding, and return type
- Infers HTTP method, path, parameters, and response shapes
- Outputs ready-to-paste PHP 8 attribute blocks following project conventions

---

## Workstream 3 — Contract Testing (Spectator)

**Package:** `hotmeteor/spectator` (dev dependency)

Spectator hooks into Laravel's test HTTP client and validates each request/response pair against `openapi.yaml` automatically — no separate test class needed.

Changes:
1. `composer require --dev hotmeteor/spectator`
2. Add `WithSpectator` trait to `tests/Feature/HealthCheck/CheckServiceHealthTest.php`
3. Existing Feature tests now validate response schemas against the spec on every assertion
4. Any mismatch between live response and declared schema fails the test

This completes the OpenAPI accuracy loop: `l5-swagger:audit` checks annotations exist, Spectator checks they're correct.

---

## Workstream 4 — CLAUDE.md Trim

Replace verbose blocks with command references. Target: reduce CLAUDE.md by ~40%.

### Add: Development Methodology section

```markdown
## Development Methodology

Implement vertically — one complete slice per increment (route → action → service → test).
Never build horizontal layers (all routes, then all models, then all services).

TDD loop per slice:
1. Write the Feature test first — defines the business contract from the API consumer's perspective
2. Confirm it fails for the right reason
3. Implement the minimal slice (route → action → service)
4. Write unit tests for components as you build them
5. Run `/validate` before moving to the next slice

Feature tests live in `tests/Feature/` — test public API behaviour via Laravel's HTTP test client.
Unit tests live in `tests/Unit/` — test individual components in isolation with mocks.
Contract tests (Spectator) run automatically within Feature tests — validate response shapes against `openapi.yaml`.
```

### Replace: Validation Gate block

Current (~15 lines of bash) → `Run /validate before marking any implementation task complete.`

### Replace: GitHub workflow block

Current (~5 lines of prose) → `Use /issue to begin work and /ship to close it.`

### Replace: Essential Commands bash block

Keep Docker service commands (up/down/build — not in any command). Remove the test/PHPStan/Pint/artisan blocks that are now covered by `/test`, `/validate`, `/ship`.

---

## Files Changed

| File | Action |
|------|--------|
| `app/Console/Commands/AuditOpenApiSpec.php` | Create |
| `.claude/commands/issue.md` | Create |
| `.claude/commands/test.md` | Create |
| `.claude/commands/validate.md` | Create |
| `.claude/commands/ship.md` | Create |
| `.claude/commands/openapi-audit.md` | Create |
| `.claude/commands/openapi-draft.md` | Create |
| `composer.json` / `composer.lock` | Modify — add `hotmeteor/spectator` (dev) |
| `tests/Feature/HealthCheck/CheckServiceHealthTest.php` | Modify — add `WithSpectator` |
| `CLAUDE.md` | Modify — Development Methodology section, trim workflow blocks |
| `docs/agentic/BUILD.md` | Modify — append Phase 7 section |

New tests required: `tests/Feature/Console/AuditOpenApiSpecTest.php` — covers undocumented, phantom, incomplete, and clean-spec scenarios.

---

## Acceptance Criteria

1. `php artisan l5-swagger:audit` exits 0 on a clean spec, non-zero when gaps exist
2. Undocumented routes, phantom OA paths, and incomplete annotations each reported correctly
3. All six `.claude/commands/` files exist and are invocable
4. `/validate` runs all five gates in sequence and stops on first failure
5. `/issue` creates a GitHub issue and branch from a `.omc/plans/` file
6. `/ship` runs `/validate` before committing — refuses to proceed if any gate fails
7. Spectator validates response schemas in existing Feature tests — no new test failures on current clean spec
8. `WithSpectator` causes a test failure if a response deviates from `openapi.yaml`
9. CLAUDE.md Development Methodology section present and accurate
10. CLAUDE.md validation gate block replaced with `/validate` reference
11. PHPStan exits 0 (empty baseline)
12. Pint exits 0 (no changes)
13. All tests pass at 100% coverage
