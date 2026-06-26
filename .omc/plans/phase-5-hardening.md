# Phase 5 — Cleanup, Refactoring & Hardening

**Status:** Approved for implementation via team  
**Approved:** 2026-04-20  
**Iteration:** 2 (Architect + Critic review complete)

---

## Task List

### A — Cleanup

**A1** Fix `tests/` permissions: `find tests/ -type d -exec chmod 755 {} \;` + `find tests/ -type f -exec chmod 644 {} \;` (from inside container as `app`)  
Acceptance: `stat` shows 755/644

**A2** Add `.dockerignore` excluding `.git/`, `vendor/`, `node_modules/`, `storage/logs/`, `.env`, `.phpunit.result.cache`, `.omc/`  
Acceptance: Docker build context reduced

**A3** Add `declare(strict_types=1);` to every file under `app/`  
Acceptance: `grep -rL "declare(strict_types" app/` returns empty

---

### B — HealthCheck domain refactor

**B1** Create `app/HealthCheck/Contracts/HealthCheckInterface.php` with `name(): string` and `check(): HealthStatusData`

**B2** Create `app/HealthCheck/Checks/MariadbHealthCheck.php` and `RedisHealthCheck.php`  
- MariaDB meta: version, max_connections, threads_connected  
- Redis meta: version, used_memory, connected_clients

**B3** Create `config/health-check.php`:
```php
return ['checks' => [MariadbHealthCheck::class, RedisHealthCheck::class]];
```

**B4** Refactor `HealthCheckerService`:
- Constructor: `iterable<HealthCheckInterface>`
- Remove `SERVICES` constant and `match` dispatch
- `checkAll()` iterates injected checkers
- `checkOne(string $name)` finds by `name()`, throws `InvalidArgumentException` if not found
- Add to `AppServiceProvider::register()`:
  - `$this->app->tag([...from config...], 'health-checks')`
  - `$this->app->bind(HealthCheckerService::class, fn($app) => new HealthCheckerService($app->tagged('health-checks')))`

**B5** Retain `GET /api/v1/health/laravel` as a runtime-only public alias:
- No `HealthCheckInterface` implementation
- Returns JSON: php_version, framework_version, environment, execution_time_ms
- Delete `LaravelHealthCheck` class if it exists
- Update OpenAPI attribute for this route

**B6** Add `runtime` field to `HealthAggregateData` (php_version, framework_version, environment)  
Populate in `CheckServiceHealth::asController()`

**B7** Update OpenAPI schemas:
- `HealthAggregateResourceSchema` gains `runtime` property
- `GET /api/v1/health/laravel` OA attribute updated

**B8** Test migration:
- Replace no-arg `HealthCheckerService` constructions with injected mock checker arrays
- Delete `test_services_constant_contains_expected_services`
- Add unit tests per checker class (`MariadbHealthCheckTest`, `RedisHealthCheckTest`)
- Add assertion that aggregate response includes `runtime` field
- Update `test_aggregate_response_has_correct_structure` to assert `runtime` key
- Update `app/HealthCheck/CLAUDE.md` "How to Add a New Service" section
- 100% coverage maintained

**Sequencing constraint:** Bindings in AppServiceProvider must be registered before old `match` block is removed.

---

### C — API hardening

**C1** Create `app/Http/Middleware/ForceJsonResponse.php`; prepend to `api` group in `bootstrap/app.php`:
```php
$middleware->api(prepend: [ForceJsonResponse::class]);
```

**C2** Feature test using `$this->get()` (no Json suffix) on protected endpoint → JSON 401

---

### D — Docker hardening

**D1** Create `docker/php/dev-entrypoint.sh`:
```sh
#!/bin/sh
chown -R ${UID:-1000}:${GID:-1000} /var/www/html/storage /var/www/html/bootstrap/cache
exec su-exec app php-fpm "$@"
```

**D2** Update Dockerfile dev stage:
- Add `su-exec` to `apk add` block
- **Remove `USER app` line** (entrypoint becomes user-switching mechanism)
- Add `ENTRYPOINT ["docker/php/dev-entrypoint.sh"]` at end of dev stage

---

### E — PHPStan

**E1** Check for `vendor/spatie/laravel-data/phpstan.neon.dist`; if present:
```yaml
includes:
    - vendor/spatie/laravel-data/phpstan.neon.dist
```
Add to `phpstan.neon`, re-run PHPStan, verify exits 0.

---

### F — CI pipeline

**F1** Add `.github/workflows/ci.yml`:
- `docker/build-push-action` with `cache-from: type=gha` and `cache-to: type=gha,mode=max`
- Build `ci` Docker target
- Run `php artisan test --compact`
- Run `./vendor/bin/phpstan analyse`
- Run `./vendor/bin/pint --test`

---

### G — Documentation

**G1** Append Phase 5 section to `docs/agentic/BUILD.md` after all tasks verified.

---

## Validation Gate (all tasks)
```bash
docker compose exec app php artisan test --coverage --compact   # 100%
docker compose exec app ./vendor/bin/phpstan analyse             # exits 0
docker compose exec app ./vendor/bin/pint --test                 # no changes
```
