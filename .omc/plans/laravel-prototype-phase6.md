# Phase 6 Plan — Swagger/OpenAPI Auth & Attribute Improvements

**Created:** 2026-04-20  
**Updated:** 2026-06-26  
**Status:** Ready to implement

## Pre-flight: Already Complete

The following steps from the original plan were completed as part of the pre-Phase-6 refactor (Phase 5):

- **Step 1** — Server URL (`{L5_SWAGGER_CONST_HOST}`) and `#[OA\SecurityScheme]` already added to `ApiController.php`
- **Step 8** — `routes/console.php` inspire closure removed; phpdoc added
- **Step 9** — `routes/api.php` phpdoc added
- **All `/api/v1/` paths** — routes are now `/api/health` and `/api/health/{service}`

**Remaining work: Steps 2–7, 10–11** (ErrorResponseSchema, meta type fix, OA response content, spec regen, validation gate, architecture docs, BUILD.md)

---

## Objective

Bring the OpenAPI spec to production quality:
- Sanctum Bearer security scheme declared and applied to protected endpoints
- Server URL corrected so Swagger UI can make real API calls
- Response schemas for error cases (503, 404)
- Enum and type accuracy improvements
- Reusable error schema introduced

---

## RALPLAN-DR Summary

### Principles
1. **Spec accuracy** — every auth-protected endpoint must declare its security requirement; the spec must reflect what the API actually returns
2. **Reuse over duplication** — complex or repeated response shapes belong in `app/OpenApi/Schemas/`; inline OA content only for simple, unique shapes
3. **Validation gate non-negotiable** — PHPStan level max (0 errors), Pint (no changes), 100% test coverage must all pass before done
4. **Minimal scope** — implement exactly what's stated; no speculative additions
5. **Follow existing conventions** — domain schema classes in `app/OpenApi/Schemas/{Domain}/`, common schemas in `app/OpenApi/Schemas/Common/`

### Decision Drivers
1. Swagger UI must render the Authorize button and lock icons correctly on protected endpoints — requires `#[OA\SecurityScheme]` + per-endpoint `security` param
2. `url: '/'` resolves to Swagger UI's own port (8081), not Laravel (8000) — API calls from the Swagger UI Authorize flow will 404 without fixing this
3. `meta` typed as `additionalProperties: string` is incorrect — service checkers return integers (e.g. `threads_connected: 5`, `connected_clients: 3`)

### Viable Options

**Option A: Attribute-only changes (no new schema files)**
- Add SecurityScheme to ApiController, security params to existing attributes, inline error content
- Pros: zero new files, all OA logic stays co-located
- Cons: `CheckServiceHealth` OA attributes grow unwieldy; error shapes duplicated if more endpoints added

**Option B: New reusable `ErrorResponseSchema` + attribute fixes (chosen)**
- Same SecurityScheme + security params
- New `app/OpenApi/Schemas/Common/ErrorResponseSchema.php` for `{"message": "..."}` shape
- Fix `meta` type, fix `status` enum references
- Pros: follows existing schema reuse pattern, keeps error shape DRY, extensible
- Cons: one additional file

**Option B chosen** — consistent with the project's existing `app/OpenApi/Schemas/` pattern.

---

## Implementation Steps

### Step 1 — Fix server URL + add SecurityScheme in `ApiController.php`

**File:** `app/Http/Controllers/ApiController.php`

**Why `{L5_SWAGGER_CONST_HOST}` not a hardcoded URL:** L5-Swagger has a `constants` substitution system (see `config/l5-swagger.php` line 317). The placeholder `{L5_SWAGGER_CONST_HOST}` is replaced at generation time with `env('L5_SWAGGER_CONST_HOST', 'http://my-default-host.com')`. This keeps environment-specific values out of source and makes the spec portable (set the env var per environment).

Changes:
1. Change `#[OA\Server(url: '/')]` → `#[OA\Server(url: '{L5_SWAGGER_CONST_HOST}')]`
2. Set `L5_SWAGGER_CONST_HOST=http://localhost:8000` in `.env` (and `.env.example`)
3. Add `#[OA\SecurityScheme]` for Sanctum Bearer:

```php
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Laravel Sanctum personal access token.',
)]
```

### Step 2 — Create `app/OpenApi/Schemas/Common/ErrorResponseSchema.php`

New reusable schema for `{"message": "..."}` error responses:

```php
#[OA\Schema(
    schema: 'ErrorResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unknown service'),
    ],
    type: 'object',
)]
class ErrorResponseSchema {}
```

### Step 3 — Fix `HealthStatusResourceSchema.php`

**File:** `app/OpenApi/Schemas/HealthCheck/HealthStatusResourceSchema.php`

Changes:
1. Fix `meta` `additionalProperties` — change from `type: 'string'` to mixed (use `OA\AdditionalProperties` without type restriction, or add `oneOf` for string|integer)
2. Keep `status` enum as inline string values (PHP enums can't be referenced directly in OA attributes; the string values `['ok', 'degraded', 'down']` are correct as-is)

### Step 4 — Update `CheckServiceHealth.php` OA attributes

**File:** `app/HealthCheck/Actions/CheckServiceHealth.php`

Changes:
1. Add `security: [['sanctum' => []]]` to `/api/health` OA\Get
2. Add `security: [['sanctum' => []]]` to `/api/health/{service}` OA\Get
3. Add `content` to the 503 response on `/api/health`:
   ```php
   new OA\Response(response: 503, description: 'One or more services degraded or down',
       content: new OA\JsonContent(ref: '#/components/schemas/HealthAggregateResource'))
   ```
4. Add `content` to the 503 response on `/api/health/{service}`:
   ```php
   new OA\Response(response: 503, description: 'Service is degraded or down',
       content: new OA\JsonContent(ref: '#/components/schemas/HealthStatusResource'))
   ```
5. Add `content` to the 404 response on `/api/health/{service}`:
   ```php
   new OA\Response(response: 404, description: 'Unknown service',
       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
   ```
6. Add `401` response to both auth-protected endpoints:
   ```php
   new OA\Response(response: 401, description: 'Unauthenticated',
       content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
   ```

### Step 5 — Regenerate spec and verify

```bash
docker compose exec app php artisan l5-swagger:generate
```

Visually verify `storage/api-docs/openapi.yaml` contains:
- `securitySchemes.sanctum` block
- `security: [{sanctum: []}]` on `/api/health` and `/api/health/{service}`
- `servers[0].url: http://localhost:8000`

### Step 6 — Run Pint

```bash
docker compose exec app ./vendor/bin/pint
```

### Step 7 — Run validation gate

```bash
docker compose exec app php artisan test --coverage --compact
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app ./vendor/bin/pint --test
```

### Step 8 — Clean up `routes/console.php`

`console.php` is the correct home for `Actions::registerCommandsForAction()` — that call is still needed. The obsolete part is the `Artisan::command('inspire', ...)` closure, which is a Laravel default and the old pattern that Actions replaces.

Changes:
1. Remove the `Artisan::command('inspire', function () { ... })` closure and its `Inspiring` import
2. Add a file-level docblock:
   ```php
   /**
    * Console route registration — Action-based CLI commands only.
    * Commands are defined via $commandSignature + asCommand() on each Action class.
    * See: https://laravelactions.com | docs/architecture/README.md
    */
   ```

### Step 9 — Add phpdoc to `routes/api.php`

Add a file-level docblock:
```php
/**
 * API routes — all controllers are Laravel Actions (AsAction trait).
 * Each Action class handles HTTP, CLI, job, and listener contexts.
 * See: https://laravelactions.com | docs/architecture/README.md
 */
```

### Step 10 — Expand `docs/architecture/README.md`

Add an "Actions & Routing" section documenting:
- Why Actions replace traditional controllers (one class = one use case = one entry point)
- How `asController()` wires to routes, `asCommand()` to console.php, `handle()` stays format-agnostic
- Link to `https://laravelactions.com`
- Note on DDD routes: domain-grouped route files (e.g. `routes/api/health.php`) deferred until multiple domains exist — document the pattern for when it's needed

### Step 11 — Update BUILD.md

Append Phase 6 section to `docs/agentic/BUILD.md`.

---

## Files Changed

| File | Action |
|------|--------|
| `app/Http/Controllers/ApiController.php` | Modify — fix server URL placeholder, add SecurityScheme |
| `.env` + `.env.example` | Modify — add `L5_SWAGGER_CONST_HOST=http://localhost:8000` |
| `app/OpenApi/Schemas/Common/ErrorResponseSchema.php` | Create — reusable error response |
| `app/OpenApi/Schemas/HealthCheck/HealthStatusResourceSchema.php` | Modify — fix meta type |
| `app/HealthCheck/Actions/CheckServiceHealth.php` | Modify — add security, 401/503/404 response content |
| `routes/console.php` | Modify — remove inspire closure, add phpdoc |
| `routes/api.php` | Modify — add phpdoc |
| `docs/architecture/README.md` | Modify — expand with Actions routing pattern |
| `docs/agentic/BUILD.md` | Modify — append Phase 6 section |

No new tests required — zero behavioral changes to PHP logic.

---

## Future Phases (Backlog)

Captured here for future cherry-picking — not yet scoped or scheduled.

| # | Theme | Summary |
|---|-------|---------|
| 7 | **Developer tooling** | `l5-swagger:audit` Artisan command; `/issue`, `/test`, `/validate`, `/ship`, `/openapi-audit`, `/openapi-draft` Claude commands; contract testing via Spectator; CLAUDE.md trim — see `.omc/plans/laravel-prototype-phase7.md` |
| — | **Authentication domain** | Sanctum token issuance and revocation endpoints (`POST /api/tokens`, `DELETE /api/tokens/{id}`); prerequisite for any user-facing API |
| — | **User domain** | Register, login, profile — builds on Authentication domain |
| — | **Observability** | Structured JSON request logging, correlation IDs, log-level config per environment |
| — | **Queue infrastructure** | Redis queue driver wired, sample job + failed-jobs table, Horizon or Supervisor config |
| — | **Database seeding** | Local developers need seeded test users and Sanctum bearer tokens ready to use. Needs: (1) `DatabaseSeeder` with user + token factories; (2) auto-reseed after `php artisan migrate` runs locally; (3) a `.claude/commands/sync.md` (`/sync`) that wraps `git pull develop + migrate + seed` so developers use `/sync` instead of `git pull` directly — avoids git hook approach since `.git/hooks/` is not tracked by git and requires per-developer setup. Decide between `/sync` command vs install script before scoping. Prerequisite: Authentication domain (token issuance must exist before tokens can be seeded). |
| — | **True E2E tests?** | Should we add tests that hit the real Docker stack (Nginx → PHP-FPM) via HTTP rather than Laravel's in-process test client? Note: slow, requires infrastructure to be running, adds maintenance cost. Feature tests + contract tests (Spectator) already cover ~95% of what this would catch — consider whether the remaining gap justifies the overhead. |

---

## Acceptance Criteria

1. `php artisan l5-swagger:generate` exits 0 and produces a valid `openapi.yaml`
2. `openapi.yaml` contains `securitySchemes.sanctum` with `type: http, scheme: bearer`
3. `/api/health` and `/api/health/{service}` operations in the spec have `security: [{sanctum: []}]`
4. Server URL in spec is `http://localhost:8000` (resolved from `L5_SWAGGER_CONST_HOST` env var)
5. 503 responses on both health endpoints have response content schemas
6. 404 response on `/api/health/{service}` has `ErrorResponse` schema
7. 401 responses declared on both auth-protected endpoints
8. `meta` field no longer restricted to string-only additionalProperties
9. `routes/console.php` has no `Artisan::command('inspire', ...)` closure; has phpdoc with laravelactions.com link
10. `routes/api.php` has phpdoc with laravelactions.com link
11. `docs/architecture/README.md` documents Actions routing pattern with laravelactions.com link and DDD routes deferral note
12. PHPStan exits 0 (empty baseline)
13. Pint exits 0 (no changes)
14. All 28 tests pass (100% coverage maintained)
