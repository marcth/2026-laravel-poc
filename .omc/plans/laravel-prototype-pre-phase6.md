# Pre-Phase-6 Plan — Routes Restructure, config/api.php, ApiVersion Middleware, ApplicationHealthCheck

**Created:** 2026-04-21
**Status:** Draft

---

## Objective

Refactor the application before Phase 6 (OpenAPI auth docs):
- Routes restructured: `routes/api/health.php`, drop `/v1/` URL prefix, `web.php` → Swagger redirect
- `config/api.php` as single source of truth for semver API version + vendor string
- `ApiVersion` middleware skeleton for header-based version negotiation (`Accept` / `X-API-Version`)
- `ApplicationHealthCheck` — new checker (maintenance mode, debug, opcache, memory) replacing the `health/laravel` closure
- `HealthAggregateData::$runtime` removed — runtime info moves to `app` service meta
- Documentation: architecture ADR, CLAUDE.md files, BUILD.md

---

## RALPLAN-DR Summary

### Principles
1. **Single source of truth** — `config/api.php` owns the API version; L5-Swagger and middleware both read it
2. **Header negotiation over URL encoding** — version in `Accept` / `X-API-Version` header; routes stay clean
3. **`app` is just another `{service}`** — no special routes or actions; `ApplicationHealthCheck` registers like any checker
4. **Validation gate non-negotiable** — PHPStan level max, Pint, 100% test coverage must all pass
5. **Minimal scope** — implement exactly what's stated; no speculative additions

### Decision Drivers
1. Dropping `/v1/` from URLs now (while the project is small) avoids cascading refactors once Phase 6 OpenAPI docs are finalised
2. `ApplicationHealthCheck` replaces the ad-hoc `/api/v1/health/laravel` closure with a proper domain-pattern checker; removes `runtime` from aggregate DTO
3. `config/api.php` must exist before Phase 6 so `{L5_SWAGGER_CONST_VERSION}` can be wired in `OA\Info`

### Viable Options

**Option A: Keep `/v1/` URL prefix, add config/api.php only**
- Pros: minimal change, no test URL updates
- Cons: URL prefix duplicates header versioning; refactor gets harder after Phase 6 adds more OA docs

**Option B: Full restructure now — drop `/v1/`, header versioning, new checker (chosen)**
- Pros: clean slate before Phase 6; routes, config, middleware, and checker all land together
- Cons: all test URLs must be updated (`/api/v1/` → `/api/`)

**Option B chosen** — the project is small enough that the URL migration is cheap now.

---

## Implementation Steps

### Stream A — Routes & Config (parallelisable)

#### A1 — `routes/web.php`
Add Swagger UI redirect; keep `web:` in `withRouting` (Swagger is the web surface):
```php
Route::get('/', fn () => redirect(env('SWAGGER_UI_URL', 'http://localhost:8081')));
```

#### A2 — `routes/console.php`
Remove `Artisan::command('inspire', ...)` closure and its `Inspiring` import. Add file-level PHPDoc:
```php
/**
 * Console command registration — Actions only.
 * Commands are defined via $commandSignature + asCommand() on each Action class.
 * @see https://laravelactions.com
 * @see docs/architecture/README.md
 */
```
Keep `Actions::registerCommandsForAction(CheckServiceHealth::class)`.

#### A3 — `routes/api/health.php` (new file)
Extract health routes; drop `/v1/` prefix:
```php
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/health', CheckServiceHealth::class);
    Route::get('/health/{service}', CheckServiceHealth::class);
});
```

#### A4 — `routes/api.php`
Thin entry point with PHPDoc; remove closure + v1 group; load domain files:
```php
/**
 * API route registration — loads per-domain route files.
 * Controllers are Laravel Actions (AsAction trait).
 * Version negotiated via Accept / X-API-Version header (see ApiVersion middleware).
 * @see https://laravelactions.com
 * @see docs/architecture/README.md
 */
require base_path('routes/api/health.php');
```

#### A5 — `config/api.php` (new file)
```php
return [
    'version' => env('API_VERSION', '1.0.0'),
    'vendor'  => env('API_VENDOR', 'laravel-prototype'),
];
```

#### A6 — `config/l5-swagger.php`
Add `L5_SWAGGER_CONST_VERSION` to `constants`:
```php
'L5_SWAGGER_CONST_VERSION' => env('API_VERSION', '1.0.0'),
```
Keep existing `L5_SWAGGER_CONST_HOST`.

#### A7 — `.env` + `.env.example`
Add:
```
API_VERSION=1.0.0
API_VENDOR=laravel-prototype
L5_SWAGGER_CONST_HOST=http://localhost:8000
SWAGGER_UI_URL=http://localhost:8081
```

---

### Stream B — ApplicationHealthCheck (parallelisable after A5)

#### B1 — `app/HealthCheck/Checks/ApplicationHealthCheck.php` (new)
Implements `HealthCheckInterface`. `name()` returns `'app'`.

Checks (environment-aware):
- `app()->isDownForMaintenance()` → `Degraded` if true
- `config('app.debug') && !app()->isLocal()` → `Degraded`
- `!ini_get('opcache.enable') && !app()->isLocal()` → `Degraded`
- `memory_limit` parsed below threshold → `Degraded` (threshold defined as `private const MIN_MEMORY_LIMIT_MB = 128` — not hardcoded inline)

`status`: `Ok` or `Degraded` only — never `Down` (by definition: if executing, not down).

`meta` includes:
```php
'api_version'       => config('api.version'),
'php_version'       => PHP_VERSION,
'framework_version' => app()->version(),
'environment'       => app()->environment(),
'maintenance_mode'  => app()->isDownForMaintenance(),
'degraded_reasons'  => $degraded,
'php_ini'           => [
    'memory_limit'        => ini_get('memory_limit'),
    'max_execution_time'  => ini_get('max_execution_time'),
    'post_max_size'       => ini_get('post_max_size'),
    'opcache_enabled'     => (bool) ini_get('opcache.enable'),
],
```

#### B2 — `config/health-check.php`
Prepend `ApplicationHealthCheck::class`:
```php
'checks' => [
    App\HealthCheck\Checks\ApplicationHealthCheck::class,
    App\HealthCheck\Checks\MariadbHealthCheck::class,
    App\HealthCheck\Checks\RedisHealthCheck::class,
],
```

#### B3 — `app/HealthCheck/Data/HealthAggregateData.php`
Remove `$runtime` property (runtime info now lives in `app` service `meta`).

#### B4 — `app/HealthCheck/Actions/CheckServiceHealth.php`
- Remove `#[OA\Get]` attribute for `/api/v1/health/laravel`
- Update remaining OA paths: `/api/v1/health` → `/api/health`, `/api/v1/health/{service}` → `/api/health/{service}`
- Remove `runtime` population in `asController()`
- Remove `runtime` from `HealthAggregateData` constructor call

#### B5 — `app/OpenApi/Schemas/HealthCheck/HealthAggregateResourceSchema.php`
Remove `runtime` property from schema.

#### B6 — `app/Http/Controllers/ApiController.php`
Update `#[OA\Info(version: '1.0.0')]` → `version: '{L5_SWAGGER_CONST_VERSION}'`.

---

### Stream C — ApiVersion Middleware (parallelisable after A5)

#### C1 — `app/Http/Middleware/ApiVersion.php` (new)
Skeleton middleware — header negotiation, no routing yet (only v1 exists):

```php
public function handle(Request $request, Closure $next): Response
{
    $version = $this->resolveVersion($request);
    app()->instance('api.version', $version);
    return $next($request);
}

private function resolveVersion(Request $request): string
{
    // X-API-Version: 1
    if ($request->hasHeader('X-API-Version')) {
        return 'v'.$request->header('X-API-Version');
    }

    // Accept: application/vnd.{vendor}.v1+json
    $accept = $request->header('Accept', '');
    $vendor = config('api.vendor');
    if (preg_match("/application\/vnd\.{$vendor}\.v(\d+)\+json/", $accept, $m)) {
        return 'v'.$m[1];
    }

    // Default: major from config('api.version')
    return 'v'.explode('.', config('api.version'))[0];
}
```

#### C2 — `bootstrap/app.php`
- Register `ApiVersion` middleware in the `api` group (after `ForceJsonResponse`)
- Whitelist `/api/health` and `/api/health/*` from `PreventRequestsDuringMaintenance`

---

### Stream D — Tests (after B + C complete)

#### D1 — `CheckServiceHealthTest.php`
- Remove `test_laravel_health_is_public` (route no longer exists)
- Remove `test_aggregate_response_includes_runtime_field` (runtime field removed from aggregate)
- Update `test_aggregate_response_has_correct_structure` — remove `runtime` from `assertJsonStructure`
- Update all URL references: `/api/v1/health` → `/api/health`, `/api/v1/health/{service}` → `/api/health/{service}`
- Add `test_app_service_returns_ok` and `test_app_service_returns_degraded_in_maintenance`

#### D2 — `tests/Unit/HealthCheck/ApplicationHealthCheckTest.php` (new)
Three tests:
- `test_check_returns_ok_in_normal_state` — mock `app()->isDownForMaintenance()` = false, local env
- `test_check_returns_degraded_when_maintenance_mode` — mock maintenance = true
- `test_check_meta_includes_api_version` — assert `meta.api_version` matches `config('api.version')`

---

### Stream E — Documentation (parallelisable throughout)

#### E1 — `docs/architecture/README.md`
Add sections:
- **API Versioning** — header negotiation (`Accept` / `X-API-Version`), `config/api.php` as source of truth, i18n alignment (`Accept-Language` same middleware pass), laravelactions.com link
- **Routes Convention** — thin `api.php` entry, `routes/api/{domain}.php` per domain, DDD split deferred until multiple domains
- **Health Check Hierarchy** — `/up` (liveness), `/api/health` (readiness aggregate), `/api/health/{service}` (drill-down); `app` is just another `{service}`

#### E2 — `app/HealthCheck/CLAUDE.md`
- Update endpoint table (remove `/api/v1/` prefix, remove `health/laravel`)
- Add `app` to service list
- Update "How to Add a New Service" — no change to pattern, just update step 4 (OA path format)

#### E3 — Root `CLAUDE.md`
- Update service URLs table
- Update essential commands section
- Update directory structure if needed

#### E4 — `docs/agentic/BUILD.md`
Append pre-Phase-6 section documenting all changes.

---

## Files Changed

| File | Action |
|------|--------|
| `routes/web.php` | Modify — Swagger redirect |
| `routes/console.php` | Modify — remove inspire, add PHPDoc |
| `routes/api.php` | Modify — thin entry point, PHPDoc |
| `routes/api/health.php` | **Create** — extracted health routes |
| `config/api.php` | **Create** — semver version + vendor |
| `config/health-check.php` | Modify — prepend ApplicationHealthCheck |
| `config/l5-swagger.php` | Modify — add L5_SWAGGER_CONST_VERSION |
| `.env` + `.env.example` | Modify — API_VERSION, API_VENDOR, SWAGGER_UI_URL |
| `app/HealthCheck/Checks/ApplicationHealthCheck.php` | **Create** |
| `app/HealthCheck/Data/HealthAggregateData.php` | Modify — remove runtime |
| `app/HealthCheck/Actions/CheckServiceHealth.php` | Modify — remove runtime + old OA attrs, update paths |
| `app/OpenApi/Schemas/HealthCheck/HealthAggregateResourceSchema.php` | Modify — remove runtime |
| `app/Http/Controllers/ApiController.php` | Modify — OA\Info version placeholder |
| `app/Http/Middleware/ApiVersion.php` | **Create** |
| `bootstrap/app.php` | Modify — register ApiVersion, whitelist maintenance |
| `tests/Feature/HealthCheck/CheckServiceHealthTest.php` | Modify — update URLs, remove obsolete tests, add app tests |
| `tests/Unit/HealthCheck/ApplicationHealthCheckTest.php` | **Create** |
| `docs/architecture/README.md` | Modify — versioning + routes ADR |
| `app/HealthCheck/CLAUDE.md` | Modify — endpoint table + app service |
| `CLAUDE.md` | Modify — URLs + commands |
| `docs/agentic/BUILD.md` | Modify — append section |

---

## Parallelisation Map (for team execution)

```
Round 1 (parallel): A1, A2, A3, A4, A5, A6, A7, E1, E3
Round 2 (parallel, after A5): B1, B2, B3, B4, B5, B6, C1, C2
Round 3 (parallel, after B + C): D1, D2, E2, E4
Round 4: Validation gate
```

---

## Acceptance Criteria

1. `GET /api/health` returns 200 (authenticated) — no `/v1/` in URL
2. `GET /api/health/app` returns service health with `meta.api_version` matching `config('api.version')`
3. `GET /api/health/app` returns `status: degraded` when maintenance mode is active
4. `GET /up` still returns 200 (untouched)
5. `GET /` redirects to Swagger UI URL
6. `config('api.version')` returns `'1.0.0'`; `config('api.vendor')` returns `'laravel-prototype'`
7. `ApiVersion` middleware binds `api.version` on every API request
8. `routes/console.php` has no `inspire` closure
9. `HealthAggregateData` has no `runtime` field
10. `app/HealthCheck/Checks/ApplicationHealthCheck.php` exists and implements `HealthCheckInterface`
11. PHPStan exits 0, Pint exits 0, all tests pass at 100% coverage
