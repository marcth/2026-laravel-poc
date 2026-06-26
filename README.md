<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Laravel Prototype

A Laravel 13 prototype serving as a team starter template and migration target for a legacy PHP application. Built agentically using Claude Code + Oh-My-ClaudeCode.

---

## Requirements

- Docker Engine 24+ (no native PHP or Composer required)
- Claude Code + Oh-My-ClaudeCode (for agentic development)

---

## Quick Start

```bash
# Start all services
docker compose up -d

# First-run setup
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

---

## Services

| Service | Description | Local URL |
|---------|-------------|-----------|
| **Laravel** | PHP 8.4-FPM application served via Nginx | http://localhost:8000 |
| **Mailpit** | Local SMTP capture — intercepts all outbound mail | http://localhost:8026 |
| **Swagger UI** | OpenAPI documentation viewer — spec at `storage/api-docs/openapi.yaml` | http://localhost:8081 |
| **MariaDB 11** | Primary relational database (MySQL-compatible) | localhost:3307 |
| **Redis 7** | Cache, sessions, and queue backend (phpredis) | localhost:6380 |

> Host ports are offset from defaults to avoid conflicts with other local Docker projects.

---

## Common Commands

```bash
# Artisan
docker compose exec app php artisan <command>

# Composer
docker compose exec app composer <command>

# Run tests
docker compose exec app php artisan test --compact

# Rebuild PHP image
docker compose build app

# View logs
docker compose logs -f app
docker compose logs -f nginx
```

---

## Testing

The project targets **100% test coverage**. Coverage is measured via Xdebug, which is pre-installed in the dev image.

```bash
# Run all tests
docker compose exec app php artisan test --compact

# Run with coverage report (must stay at 100%)
docker compose exec app php artisan test --coverage --compact

# Run a specific test file
docker compose exec app php artisan test --compact tests/Feature/HealthCheck/CheckServiceHealthTest.php

# Run a specific test by name
docker compose exec app php artisan test --compact --filter=test_check_redis_returns_ok
```

### Static Analysis

PHPStan runs at **level max** with an empty baseline — no errors are suppressed.

```bash
# PHPStan (must exit 0, no errors)
docker compose exec app ./vendor/bin/phpstan analyse

# Pint code style (must produce no changes)
docker compose exec app ./vendor/bin/pint --test
```

All three gates (tests at 100%, PHPStan clean, Pint clean) must pass before any change is considered complete.

---

## Architecture

This project follows a siloed domain architecture under `app/{Domain}/`. See [docs/architecture/README.md](docs/architecture/README.md) for design decisions, ADRs, and diagrams.

| Domain | Purpose | Docs |
|--------|---------|------|
| HealthCheck | Service liveness and readiness checks | [app/HealthCheck/CLAUDE.md](app/HealthCheck/CLAUDE.md) |

---

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13.x |
| PHP | 8.4-fpm-alpine (multi-stage: base / dev / ci / release) |
| Web server | Nginx Alpine → PHP-FPM |
| Database | MariaDB 11 |
| Cache / Sessions / Queues | Redis 7 (phpredis) |
| Mail | Mailpit |
| API Docs | Swagger UI |

---

## Agentic Development

This project uses [Laravel Boost](https://laravel.com/docs/ai) with an MCP server configured in `mcp.json`. Claude Code loads it automatically, providing tools for documentation search, database inspection, and browser log access.

See `CLAUDE.md` for full agentic engineering instructions and `docs/agentic/BUILD.md` for the living build log.

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
