# Architecture

> Work in progress — detailed documentation will be added in Phase 5.

## Domain Structure

This project uses a siloed domain architecture. Each domain lives under `app/{Domain}/` and is self-contained:

```
app/{Domain}/
├── Actions/    # One class per use case (lorisleiva/laravel-actions)
├── Data/       # Spatie Data DTOs — request input and response output
├── Services/   # External service calls, data access
├── Enums/      # Domain enumerations
└── CLAUDE.md   # Domain documentation (required)
```

## Domains

| Domain | Purpose | Entry Point | Docs |
|--------|---------|-------------|------|
| HealthCheck | Service liveness and readiness checks | `GET /api/health` | [README](../../app/HealthCheck/README.md) · [CLAUDE.md](../../app/HealthCheck/CLAUDE.md) |

## Key Design Decisions

ADRs will be added here as the project evolves. For the current build log and rationale behind each phase, see [docs/agentic/BUILD.md](../agentic/BUILD.md).

---

## API Versioning

Version is negotiated via request headers — never in the URL path:

- `Accept: application/vnd.laravel-prototype.v{major}+json` — standard content negotiation
- `X-API-Version: {major}` — convenience header for clients that cannot set `Accept`

`config/api.php` is the **single source of truth** for the semver version string and vendor identifier. Both the `ApiVersion` middleware and the L5-Swagger `{L5_SWAGGER_CONST_VERSION}` constant read from this config.

The `ApiVersion` middleware resolves the negotiated version on every API request and binds it as `api.version` in the service container. It defaults to the major version from `config('api.version')` when no header is present.

i18n follows the same pattern: `Accept-Language` is negotiated in the same middleware pass, keeping version and locale negotiation consistent.

See [https://laravelactions.com](https://laravelactions.com) for how Actions serve as both HTTP controllers and CLI commands.

---

## Routes Convention

`routes/api.php` is a **thin entry point** that loads per-domain route files:

```
routes/
├── api.php              # Entry point — requires per-domain files, PHPDoc only
├── api/
│   └── health.php       # HealthCheck domain routes
├── web.php              # Web surface (Swagger UI redirect)
└── console.php          # Console command registration
```

Each domain owns its route file (`routes/api/{domain}.php`). Domain-grouped subdirectories (e.g. `routes/api/v1/`) are deferred until multiple major versions coexist.

Version is **not** encoded in the URL — `/api/health`, not `/api/v1/health`. Header negotiation handles versioning (see API Versioning above).

---

## Health Check Hierarchy

Three endpoints, each serving a distinct infrastructure concern:

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /up` | Public | Liveness — Laravel built-in, plain 200/503, for Docker health checks and load balancers |
| `GET /api/health` | `auth:sanctum` | Readiness aggregate — runs all registered checkers (mariadb, redis, app) |
| `GET /api/health/{service}` | `auth:sanctum` | Single-service drill-down |

`app` is **just another `{service}`** — `ApplicationHealthCheck` registers like any other checker via `config/health-check.php`. There is no special route or action for it.

Checkers return `Ok` or `Degraded` only (never `Down` — by definition, if the process is executing a checker, the app is not down).
