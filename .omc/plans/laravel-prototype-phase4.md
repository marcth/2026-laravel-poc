# Plan: Laravel Prototype — Phase 4 (HealthCheck Domain)

## ADR
- **Decision:** Siloed `HealthCheck` domain with Laravel Actions + Spatie Data; pure `application/json` REST; multi-context I/O via `lorisleiva/laravel-actions`
- **Drivers:** Format-agnostic business logic, type-safe boundaries, standardised multi-context I/O (HTTP/CLI/job), agentic discoverability (one file = one use case)
- **Alternatives considered:** `timacdonald/json-api` + content negotiation (deferred to Phase 5 — no real client yet), separate Artisan command (rejected — `laravel-actions` is the project-wide standard I/O pattern), drop `/up` re-implementation (accepted — keep framework `/up` untouched)
- **Why chosen:** Minimal coupling; explicit data flow; full stack exercised on a real use case (service readiness); pattern is repeatable across all future domains
- **Consequences:** `app/OpenApi/Schemas/` requires manual schema maintenance; `HealthStatusData::$meta` typed as `array` (typed sub-DTOs in Phase 5); dual `#[OA\Get]` attributes on single `asController()` method
- **Follow-ups:** JSON:API content negotiation (Phase 5), typed meta sub-DTOs (Phase 5), Sanctum token issuance (Phase 5)

## RALPLAN-DR Summary
- **Principles:** One class/one use case, format-agnostic domain, type-safe boundaries, one health check source of truth, OpenAPI-first
- **Decision Drivers:** Multi-context I/O standard, `/up` liveness owned by framework, pure REST for Phase 4
- **Chosen Option:** `laravel-actions` + `spatie/laravel-data`, `HealthCheck` domain, framework `/up` retained

## Packages to Install
```bash
docker compose exec app composer require lorisleiva/laravel-actions spatie/laravel-data
```

## Domain Structure
```
app/HealthCheck/
├── Actions/
│   └── CheckServiceHealth.php            # single action, all contexts
├── Data/
│   └── HealthStatusData.php              # DTO: service, status, code, execution_time_ms, meta[]
├── Services/
│   └── HealthCheckerService.php          # per-service ping logic, 2s timeout per check
├── Http/
│   └── Resources/
│       ├── HealthStatusResource.php      # single service → application/json
│       └── HealthAggregateResource.php   # all services → {services, healthy, checked_at}
├── Enums/
│   └── ServiceStatus.php                 # ok | degraded | down
└── CLAUDE.md                             # domain documentation
```

## Routes
| Route | File | Auth | Purpose |
|-------|------|------|---------|
| `GET /up` | framework (untouched) | Public | Framework liveness — zero changes |
| `GET /api/v1/health/laravel` | `routes/api.php` | Public | Lightweight laravel check, zero I/O |
| `GET /api/v1/health` | `routes/api.php` | auth:sanctum | All services aggregate, 200/503 |
| `GET /api/v1/health/{service}` | `routes/api.php` | auth:sanctum | Single service drill-down |

**Important:** `/health/laravel` must be registered BEFORE `/{service}` wildcard.

## Data Flow
```
GET /api/v1/health/laravel
  → CheckServiceHealth::asController(service: 'laravel')
  → handle('laravel') → HealthStatusData
  → new HealthStatusResource($dto) → JsonResponse

GET /api/v1/health
  → CheckServiceHealth::asController(service: null)
  → handle(null) → HealthStatusData[]
  → new HealthAggregateResource($results) → JsonResponse {services, healthy, checked_at}

GET /api/v1/health/{service}
  → CheckServiceHealth::asController(service: 'redis')
  → handle('redis') → HealthStatusData
  → new HealthStatusResource($dto) → JsonResponse

CLI: php artisan health:check {?service}
  → CheckServiceHealth::asCommand()
  → handle(null|$service)
  → $command->table([...]) output
```

## Key Classes

### `ServiceStatus` Enum
```php
enum ServiceStatus: string {
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Down = 'down';
}
```

### `HealthStatusData`
```php
class HealthStatusData extends Data {
    public function __construct(
        public readonly string $service,
        public readonly ServiceStatus $status,
        public readonly int $code,
        public readonly int $executionTimeMs,
        public readonly array $meta = [],
    ) {}
}
```

### `HealthCheckerService`
Sequential checks, explicit 2s timeout per service:
- `checkLaravel(): HealthStatusData` — zero I/O; `framework_version`, `php_version`, `environment` in meta
- `checkMariadb(): HealthStatusData` — `DB::connection()->getPdo()` with `PDO::ATTR_TIMEOUT => 2`
- `checkRedis(): HealthStatusData` — `Redis::ping()` with `OPT_READ_TIMEOUT => 2.0`
- Known services: `['laravel', 'mariadb', 'redis']`
- Unknown service: throws `InvalidArgumentException` → 404 in controller

### `CheckServiceHealth` action
```php
class CheckServiceHealth {
    use AsAction;

    public string $commandSignature = 'health:check {service? : Service name (omit for all)}';

    public function handle(?string $service = null): HealthStatusData|array { ... }

    // Two #[OA\Get] attribute blocks — /api/v1/health and /api/v1/health/{service}
    public function asController(Request $request, ?string $service = null): JsonResponse { ... }

    public function asCommand(Command $command): void {
        // $command->table() output, no auth
    }
}
```

### Response shapes

Single service:
```json
{
  "service": "redis",
  "status": "ok",
  "code": 200,
  "execution_time_ms": 3,
  "meta": { "memory_used": "1.2M", "connected_clients": 2 }
}
```

Aggregate:
```json
{
  "services": [...],
  "healthy": true,
  "checked_at": "2026-04-20T12:00:00Z"
}
```

## OpenAPI
- `CheckServiceHealth::asController()` carries two `#[OA\Get]` attribute blocks (distinct paths)
- `app/OpenApi/Schemas/HealthCheck/HealthStatusResourceSchema.php`
- `app/OpenApi/Schemas/HealthCheck/HealthAggregateResourceSchema.php`
- `ApiController.php` retained with `#[OA\Info]` and `#[OA\Server]` only — `health()` method removed

## ApiController Migration
- Remove `Route::get('/health', ...)` from `routes/api.php`
- Remove `health()` method from `ApiController.php`
- Retain `#[OA\Info]` and `#[OA\Server]` in `ApiController.php`

## Auth
- `/api/v1/health/laravel` — no middleware (public)
- `/api/v1/health` and `/api/v1/health/{service}` — `auth:sanctum`
- Tests: `$this->actingAs(User::factory()->create(), 'sanctum')` for protected routes

## Domain CLAUDE.md contents
- Purpose: service readiness checks for DevOps/CI/CD/local dev
- Consumers: monitoring (pings /health/laravel), deployment pipelines (/health), local developers (/health/{service}), CLI
- How to add a new service checker: add method to `HealthCheckerService`, add to known services registry, add meta fields as needed
- Base response shape: `service`, `status`, `code`, `execution_time_ms`, `meta`
- Public: `/health/laravel`; Secured: `/health`, `/health/{service}`; CLI: no auth
- Timeout budget: 2s per check, sequential

## Root CLAUDE.md update
Add convention: every domain directory gets a `CLAUDE.md` documenting purpose, consumers, extension pattern, and auth model.

## Acceptance Criteria
- [ ] `composer require lorisleiva/laravel-actions spatie/laravel-data` installs cleanly
- [ ] `GET /up` returns 200 (framework untouched — zero changes to `bootstrap/app.php`)
- [ ] `GET /api/v1/health/laravel` public, returns HealthStatusResource JSON shape
- [ ] `GET /api/v1/health` returns HealthAggregateResource JSON, 200 all healthy, 503 any down
- [ ] `GET /api/v1/health/{service}` returns HealthStatusResource JSON, 200/503 known, 404 unknown
- [ ] All protected routes return 401 without auth
- [ ] `php artisan health:check` outputs all services as formatted table
- [ ] `php artisan health:check redis` outputs single service as table
- [ ] `php artisan l5-swagger:generate` produces valid spec for all three endpoints
- [ ] Old `GET /api/health` route removed; `ApiController::health()` removed; OA base annotations retained
- [ ] All pre-existing tests pass after `/api/health` removal
- [ ] `app/HealthCheck/CLAUDE.md` documents domain conventions
- [ ] Root `CLAUDE.md` updated with domain CLAUDE.md convention
- [ ] `docs/agentic/BUILD.md` updated with Phase 4 decisions

## Tests

### Feature: `tests/Feature/HealthCheck/CheckServiceHealthTest.php`
- `test_framework_up_route_untouched()` — GET /up 200
- `test_laravel_health_is_public()` — no auth, HealthStatusResource shape
- `test_health_aggregate_returns_200_when_all_healthy()` — actingAs sanctum; services[], healthy=true
- `test_health_aggregate_returns_503_when_service_down()` — mock HealthCheckerService one failure
- `test_single_service_check_redis()` — actingAs sanctum
- `test_single_service_check_mariadb()` — actingAs sanctum
- `test_unknown_service_returns_404()` — actingAs sanctum
- `test_health_requires_auth()` — expects 401
- `test_health_service_requires_auth()` — expects 401
- `test_aggregate_response_has_correct_structure()` — assert services[], healthy, checked_at keys

### Unit: `tests/Unit/HealthCheck/HealthCheckerServiceTest.php`
- DB/Redis mocks for timeout and failure scenarios per service

## Risk Flags
- `/health/laravel` must be registered before `/{service}` wildcard in `routes/api.php`
- Old `/api/health` route removed — verify no existing tests reference it
- `HealthStatusData::$meta` typed as `array` — typed sub-DTOs in Phase 5
- Two `#[OA\Get]` attributes on single `asController()` method — document in CLAUDE.md
