# Agentic Build Reference

Current state of the agentic build. History is tracked in git.

---

## Local System Requirements

No native PHP or Composer required — all PHP execution runs inside Docker containers.

| Tool | Minimum Version | Purpose |
|------|----------------|---------|
| **Docker** | 24+ | Runs all services and PHP-FPM |
| **Claude Code** | 2.x | AI coding agent (CLI) |
| **Oh-My-ClaudeCode** | 4.x | Multi-agent orchestration (autopilot, ralplan, deep-interview) |

---

## Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| Framework | Laravel 13.5.0 | Latest; chosen over LTS for currency as a prototype template |
| PHP | 8.4-fpm-alpine | Required by Symfony 8.x (Laravel 13 dependency) |
| Web server | Nginx Alpine → PHP-FPM | Web root: `public/` |
| Database | MariaDB 11 | MySQL-compatible; `DB_CONNECTION=mysql` |
| Cache / Sessions / Queues | Redis 7 (phpredis) | PECL extension, not predis |
| Mail | Mailpit | Local SMTP capture |
| API Docs | Swagger UI | Spec at `storage/api-docs/openapi.yaml` |

---

## Docker Services

| Service | Image | Host Port | Purpose |
|---------|-------|-----------|---------|
| app | custom PHP 8.4-fpm-alpine | — | Laravel PHP-FPM |
| nginx | nginx:alpine | 8000 | Reverse proxy → PHP-FPM |
| mariadb | mariadb:11 | 3307 | Primary relational database |
| redis | redis:7-alpine | 6380 | Cache, sessions, queues |
| mailpit | axllent/mailpit | 8026 (UI) / 1026 (SMTP) | Local email capture |
| swagger-ui | swaggerapi/swagger-ui | 8081 | OpenAPI documentation |

> Host ports are offset from defaults to avoid conflicts with co-resident Docker projects.

---

## Dockerfile — Multi-Stage Alpine

| Stage | Extras | Purpose |
|-------|--------|---------|
| `base` | pdo_mysql, intl, bcmath, zip, mbstring, phpredis, Composer 2 | Shared foundation |
| `dev` | Xdebug 3 (debug + coverage) | Local development |
| `ci` | PCOV, APP_ENV=testing | CI coverage runs |
| `release` | OPcache, storage permissions, healthcheck | Production |

**Key decisions:**
- Alpine over Debian: ~100 MB vs ~500 MB; smaller attack surface
- `linux-headers` required in dev stage for Xdebug on Alpine (`rtnetlink.h`)
- `$PHPIZE_DEPS` pattern: install build tools → compile → remove to minimise layer size
- OPcache intentionally off in dev/ci for live code changes
- Composer 2 injected via `COPY --from=composer:2`

---

## Laravel Boost MCP

MCP server configured in `mcp.json` at the project root. Claude Code loads it automatically.

```json
{
    "mcpServers": {
        "laravel-boost": {
            "type": "stdio",
            "command": "docker",
            "args": ["compose", "exec", "-T", "app", "php", "artisan", "boost:mcp"],
            "cwd": "."
        }
    }
}
```

`cwd: "."` keeps the path portable. `-T` disables TTY for stdio transport.

| Tool | Purpose |
|------|---------|
| `search-docs` | Version-specific Laravel/package docs — run before coding |
| `database-query` | Read-only DB queries without tinker |
| `database-schema` | Inspect table structure before migrations/models |
| `get-absolute-url` | Resolve correct URL before sharing with user |
| `browser-logs` | Read browser errors and exceptions |

---

## Known Pitfalls

- **Storage permissions:** Docker runs as root; files created inside containers may be root-owned on the host. Fix: `docker compose exec app chmod -R 775 storage bootstrap/cache`
- **Xdebug connection warnings:** `xdebug.start_with_request=yes` logs a connection attempt on every request when no IDE debugger is listening — expected, not an error
- **Port conflicts:** Default ports (80, 3306, 6379, 8025, 8080) are occupied on this machine by `wa-api`. All ports are offset — see service table above

---

## Agentic Specs & Plans

| File | Purpose |
|------|---------|
| `.omc/specs/deep-interview-laravel-prototype-phase1.md` | Requirements spec (deep interview, 21% ambiguity) |
| `.omc/plans/laravel-prototype-phase1.md` | Phase 1 consensus plan |
| `.omc/plans/laravel-prototype-phase2.md` | Phase 2 consensus plan |

---

## Phase 3 — Composer Packages

**Packages added:** `darkaonline/l5-swagger ^11.0`, `laravel/sanctum ^4.3` (installed as a side-effect of `php artisan install:api`)

### L5-Swagger (OpenAPI spec generation)

Replaces the static placeholder `storage/api-docs/openapi.yaml` with a spec auto-generated from PHP 8 attributes.

**Key configuration changes to `config/l5-swagger.php`:**
- `docs_yaml` → `openapi.yaml` (matches Swagger UI container's `SWAGGER_JSON` env var)
- `format_to_use_for_docs` → `yaml`

**swagger-php v6 uses PHP 8 attributes** (not docblock annotations):
```php
use OpenApi\Attributes as OA;

#[OA\Info(title: 'Laravel Prototype API', version: '1.0.0')]
#[OA\Server(url: '/', description: 'Local development')]
```

Base annotation lives in `app/Http/Controllers/ApiController.php`. Run `php artisan l5-swagger:generate` after adding new `#[OA\...]` attributes.

### API Routes

`php artisan install:api` created `routes/api.php` and migrated the `personal_access_tokens` table. A `GET /api/health` endpoint was added as the first documented route.

### Sanctum

Installed as a dependency of `install:api`. Not yet configured — auth/ACL deferred to a future phase after the development architecture is established.


---

## Phase 4 — Development Architecture (Planned)

### Decisions from Interview (2026-04-20)

**Architecture:** Siloed domains + Laravel Actions + Spatie Data + JSON:API content negotiation

**Domain structure:**
```
app/
├── {Domain}/
│   ├── Actions/      # One class per use case (lorisleiva/laravel-actions)
│   ├── Services/     # Data access layer (DB calls)
│   └── Data/         # DTOs (spatie/laravel-data)
```

**Planned packages:**
| Package | Purpose |
|---------|---------|
| `lorisleiva/laravel-actions` | Multi-context actions (HTTP, job, CLI, listener, object) |
| `spatie/laravel-data` | Type-safe DTOs for requests and responses |
| `timacdonald/json-api` | JSON:API serialization wrapping API Resources |

**Content negotiation:** Middleware inspects `Accept` header, routes to JSON or JSON:API serializer. Actions stay format-agnostic — `handle()` returns a domain DTO, `asController()` returns an API Resource.

**API:** `routes/api.php` versioned at `/v1/` (e.g. `api.laravel-prototype.com/v1/user/register`)
**Web:** `routes/web.php` shares same action business logic

**Open question before Phase 4 implementation:** How to generate detailed OpenAPI specs that accurately reflect content negotiation (JSON vs JSON:API response shapes per `Accept` header)?

**Design principle:** Human-readable + agentic-development-first. One class = one use case = one entry point for agents and developers alike.

---

## Phase 4 — HealthCheck Domain

### Packages Added
| Package | Purpose |
|---------|---------|
| `lorisleiva/laravel-actions` | Multi-context actions (HTTP, CLI, job, listener) via `AsAction` trait |
| `spatie/laravel-data` | Type-safe DTOs for domain boundaries |
| `phpstan/phpstan` | Static analysis at level max; empty baseline (`phpstan-baseline.neon`) — no errors suppressed |

### Architecture Pattern
One class = one use case. `CheckServiceHealth` handles HTTP requests, Artisan CLI, and future job/listener contexts via `AsAction`. `handle()` contains pure business logic; `asController()` returns DTOs directly via `response()->json($dto)`; `asCommand()` formats output as a table.

**DTOs as responses:** `asController()` returns Spatie Data DTOs directly — no `JsonResource` wrapper. `response()->json($dto)` calls `Data::jsonSerialize()` which runs the full Spatie Data transformer pipeline (enum → value, camelCase → snake_case via `#[MapOutputName(SnakeCaseMapper::class)]`). This preserves type safety end-to-end.

### Domain Structure
```
app/{Domain}/
├── Actions/   # One class per use case (AsAction)
├── Data/      # Spatie Data DTOs — also serve as HTTP response objects
├── Services/  # Data access / external service calls
├── Enums/     # Domain enumerations
└── CLAUDE.md  # Domain documentation (required for all domains)
```

### HealthCheck Endpoints
| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /up` | Public | Framework liveness (Laravel built-in, untouched) |
| `GET /api/v1/health/laravel` | Public | Lightweight app check, zero I/O, for monitoring pings |
| `GET /api/v1/health` | auth:sanctum | All services aggregate — 200 healthy, 503 any down |
| `GET /api/v1/health/{service}` | auth:sanctum | Single service drill-down with meta |

### CLI
`php artisan health:check {?service}` — same business logic as HTTP, no auth, formatted table output.

### Conventions Established
- **Domain CLAUDE.md**: every domain directory gets a `CLAUDE.md` — see `app/HealthCheck/CLAUDE.md` as the template
- **API versioning**: all API routes under `/v1/` prefix in `routes/api.php`
- **OpenAPI schemas**: `app/OpenApi/Schemas/{Domain}/` for reusable schema classes

---

## Docker Non-Root User Fix

### Problem
Files created inside the container (`php artisan test`, cache writes, etc.) were owned by `root` on the host because PHP-FPM defaults to `www-data` and `docker compose exec` ran as root.

### Solution
Three-part fix so container workers match the host developer's UID/GID:

1. **`Dockerfile` dev stage** — build args `ARG UID/GID`, create `app` user with matching UID/GID, set `USER app`
2. **`docker/php/fpm-dev.conf`** — PHP-FPM pool override (`user = app`, `group = app`), copied as `zzz-dev.conf` so it loads last (alphabetical order) and overrides `www.conf`
3. **`docker-compose.yml`** — `user: "${UID:-1000}:${GID:-1000}"` on the app service; build args forwarded from `.env`

### Setup Required
Add to `.env` (or run `echo "UID=$(id -u)" >> .env && echo "GID=$(id -g)" >> .env`):
```
UID=1000
GID=1000
```
Then rebuild: `docker compose build app && docker compose up -d`.

### Verification
```bash
docker compose exec app whoami   # → app (not root)
```

---

## Phase 5 — Cleanup, Refactoring & Hardening

**Completed:** 2026-04-20 | **Validation:** 28 tests (75 assertions), PHPStan level max (0 errors), Pint (49 files clean)

### A — Cleanup

- **A1** `tests/` permissions reset to 755/644, ownership transferred to host user.
- **A2** `.dockerignore` added — excludes `.git/`, `vendor/`, `node_modules/`, `storage/logs/`, `.env`, `.phpunit.result.cache`, `.omc/`.
- **A3** `declare(strict_types=1)` added to all 11 `app/` PHP files that were missing it.

### B — HealthCheck Domain Refactor

The HealthCheck domain was restructured around a `HealthCheckInterface` contract:

- **`app/HealthCheck/Contracts/HealthCheckInterface.php`** — `name(): string` + `check(): HealthStatusData`.
- **`app/HealthCheck/Checks/MariadbHealthCheck.php`** — meta: `version`, `max_connections`, `threads_connected`.
- **`app/HealthCheck/Checks/RedisHealthCheck.php`** — meta: `version`, `used_memory`, `connected_clients`.
- **`config/health-check.php`** — registry of checker classes; drives IoC binding.
- **`HealthCheckerService`** — constructor now takes `iterable<HealthCheckInterface>`; `SERVICES` constant and inline `match` block removed. `AppServiceProvider` tags and binds checkers from config.
- **`GET /api/v1/health/laravel`** — decoupled from `HealthCheckerService`; now a standalone runtime-info closure returning `php_version`, `framework_version`, `environment`, `execution_time_ms`.
- **`HealthAggregateData`** — gains `runtime` field (`php_version`, `framework_version`, `environment`), populated by `CheckServiceHealth::asController()`.
- **OpenAPI** — `HealthAggregateResourceSchema` updated with `runtime` property; `/api/v1/health/laravel` OA attribute updated to new shape.
- **Adding a new checker:** implement `HealthCheckInterface`, register in `config/health-check.php`. No changes to `HealthCheckerService` needed.

### C — API Hardening

- **`app/Http/Middleware/ForceJsonResponse.php`** — sets `Accept: application/json` on all API requests; prepended to the `api` middleware group in `bootstrap/app.php`.

### D — Docker Hardening

- **`docker/php/dev-entrypoint.sh`** — `su-exec` entrypoint that `chown`s `storage/` and `bootstrap/cache/` to `${UID}:${GID}` before switching to the `app` user.
- **Dockerfile dev stage** — `su-exec` added to `apk add`; `USER app` removed; `ENTRYPOINT` set to `dev-entrypoint.sh`. Storage permission issues on bind-mounts resolved without requiring host `sudo`.

### E — PHPStan

- `vendor/spatie/laravel-data/phpstan.neon.dist` is an internal dev config — including it at level max floods with vendor errors. Left `phpstan.neon` unchanged; PHPStan exits 0 with an empty baseline.

### F — CI Pipeline

- **`.github/workflows/ci.yml`** — builds the `ci` Docker target with GHA layer cache (`type=gha`); spins up `mariadb:11` and `redis:7-alpine` as services; runs `php artisan test --compact`, `phpstan analyse`, and `pint --test` inside the container.

### Test Coverage

- `HealthCheckerServiceTest` rewritten for injected-checker pattern; `test_services_constant_contains_expected_services` deleted.
- `MariadbHealthCheckTest` and `RedisHealthCheckTest` added (3 tests each).
- `CheckServiceHealthTest` updated: `test_laravel_health_is_public` asserts new runtime shape; `test_aggregate_response_has_correct_structure` and `test_aggregate_response_includes_runtime_field` assert `runtime` field.
- `ForceJsonResponseTest` added (3 tests) — verifies JSON enforcement without `Accept` header.

Note: this fix only applies to the `dev` stage. `ci` and `release` stages are unaffected.

---

## Pre-Phase-6 Refactor — Routes, config/api.php, ApiVersion Middleware, ApplicationHealthCheck

**Completed:** 2026-04-21 | **Goal:** Clean slate before Phase 6 (OpenAPI auth docs) — drop `/v1/` URL prefix, add header-based versioning, replace ad-hoc laravel health closure with a proper domain checker.

### A — Routes Restructure

- **`routes/api/health.php`** (new) — HealthCheck routes extracted from `routes/api.php`; `/v1/` prefix dropped. Routes are now `GET /api/health` and `GET /api/health/{service}`.
- **`routes/api.php`** — Thinned to a PHPDoc-only entry point that `require`s per-domain files. Version negotiation note added.
- **`routes/web.php`** — Swagger UI redirect added: `GET /` → `env('SWAGGER_UI_URL')`.
- **`routes/console.php`** — `Artisan::command('inspire', ...)` closure and `Inspiring` import removed; file-level PHPDoc added.

### B — config/api.php (Single Source of Truth)

- **`config/api.php`** (new) — Owns `api.version` (semver, from `API_VERSION` env) and `api.vendor` (from `API_VENDOR` env).
- **`config/l5-swagger.php`** — `L5_SWAGGER_CONST_VERSION` constant added, reads from `API_VERSION` env. Used in `OA\Info(version: '{L5_SWAGGER_CONST_VERSION}')`.
- **`.env` / `.env.example`** — `API_VERSION=1.0.0`, `API_VENDOR=laravel-prototype`, `SWAGGER_UI_URL=http://localhost:8081` added.

### C — ApiVersion Middleware

- **`app/Http/Middleware/ApiVersion.php`** (new) — Resolves negotiated API version from `X-API-Version` header or `Accept: application/vnd.{vendor}.v{n}+json` media type; defaults to major from `config('api.version')`; binds resolved version as `api.version` in the service container.
- **`bootstrap/app.php`** — `ApiVersion` registered in the `api` middleware group (after `ForceJsonResponse`); `/api/health` and `/api/health/*` whitelisted from `PreventRequestsDuringMaintenance`.

### D — ApplicationHealthCheck

- **`app/HealthCheck/Checks/ApplicationHealthCheck.php`** (new) — Implements `HealthCheckInterface`. `name()` returns `'app'`. Checks: maintenance mode, debug flag in non-local env, opcache disabled in non-local env, memory limit below 128 MB threshold. Returns `Ok` or `Degraded` only — never `Down` (by definition, if executing, not down). `meta` includes `api_version`, `php_version`, `framework_version`, `environment`, `maintenance_mode`, `degraded_reasons`, and `php_ini` snapshot.
- **`config/health-check.php`** — `ApplicationHealthCheck::class` prepended to `checks` array; `app` is now the first checker alongside `mariadb` and `redis`.
- **`app/HealthCheck/Data/HealthAggregateData.php`** — `$runtime` property removed; runtime info now lives in `app` service `meta`.
- **`app/HealthCheck/Actions/CheckServiceHealth.php`** — `/api/v1/health/laravel` `#[OA\Get]` attribute removed; remaining OA paths updated to `/api/health` and `/api/health/{service}`; `runtime` population removed from `asController()`.
- **`app/OpenApi/Schemas/HealthCheck/HealthAggregateResourceSchema.php`** — `runtime` property removed from schema.
- **`app/Http/Controllers/ApiController.php`** — `OA\Info version` updated to `'{L5_SWAGGER_CONST_VERSION}'`.

### E — Tests

- **`tests/Feature/HealthCheck/CheckServiceHealthTest.php`** — All URLs updated (`/api/v1/` → `/api/`); `test_laravel_health_is_public` removed (route gone); `test_aggregate_response_includes_runtime_field` removed (field gone); `runtime` removed from `assertJsonStructure`; `test_app_service_returns_ok` and `test_app_service_returns_degraded_in_maintenance` added.
- **`tests/Unit/HealthCheck/ApplicationHealthCheckTest.php`** (new) — Three tests: normal state returns `Ok`, maintenance mode returns `Degraded`, `meta.api_version` matches `config('api.version')`.

### F — Documentation

- **`docs/architecture/README.md`** — Domain table URL updated; API Versioning, Routes Convention, and Health Check Hierarchy sections added.
- **`app/HealthCheck/CLAUDE.md`** — Endpoint table updated (removed `/api/v1/health/laravel` row, dropped `/v1/` prefix); `app` service documented; OA path format note updated; Architecture Notes updated.
- **Root `CLAUDE.md`** — Stack section port numbers corrected (Mailpit :8026, Swagger UI :8081); routes directory entry updated.

### Validation

All gates passed: PHPStan level max (0 errors), Pint (clean), tests at 100% coverage.

---

## Phase 6 — OpenAPI Auth & Attribute Improvements

**Completed:** 2026-04-23 | **Goal:** Harden OpenAPI spec accuracy — Sanctum Bearer security scheme, reusable error schema, and correct response annotations on HealthCheck endpoints. Zero behavioral changes to PHP logic.

### A — ApiController.php

- **`OA\Server` restored** — `url: '{L5_SWAGGER_CONST_HOST}'` constant (resolved by l5-swagger from `L5_SWAGGER_CONST_HOST` env) replaces the hardcoded `/` placeholder. Allows the generated spec to reflect the correct host at generation time.
- **`OA\SecurityScheme` added** — declares `sanctum` as a `http` / `bearer` / `bearerFormat: JWT` security scheme; referenced by secured endpoints.

### B — ErrorResponseSchema.php

- **`app/OpenApi/Schemas/Common/ErrorResponseSchema.php`** (new) — reusable OpenAPI schema for the standard Laravel error envelope: `{"message": "string"}`. Referenced by 401, 404, and 503 response definitions across all secured endpoints.

### C — HealthStatusResourceSchema.php

- **`meta` property** — `additionalProperties` changed from `type: string` to `true` (unrestricted). This correctly represents the mixed-type `meta` map returned by checkers (e.g. `MariadbHealthCheck` returns integer `threads_connected`; `ApplicationHealthCheck` returns booleans and arrays).

### D — CheckServiceHealth.php

- **`security: [{sanctum: []}]`** added to both `GET /api/health` (aggregate) and `GET /api/health/{service}` (single-service) OA attributes — reflects existing `auth:sanctum` middleware on these routes.
- **401 response** added to both endpoints — references `ErrorResponseSchema`; triggered when no valid Bearer token is provided.
- **503 response** (aggregate) and **404 response** (single-service) — updated to include `content` schema references to `ErrorResponseSchema` for spec completeness.
- **Service `enum` updated** — `laravel` entry replaced with `app` to match the `ApplicationHealthCheck` checker introduced in the Pre-Phase-6 refactor.

### E — No Test Changes

All changes are OpenAPI annotation-only. PHP logic, routing, middleware, and data layer are unchanged. Existing 100% test coverage is preserved without modification.
