# CLAUDE.md — laravel-prototype

Agentic project instructions for Claude Code. Read this file before making any changes.

---

## Project Purpose

A Laravel 13 prototype that evolves through defined phases:

1. **Phase 1 (complete):** Clean Laravel 13 install, agentic build documentation, CLAUDE.md
2. **Phase 2 (complete):** Custom Docker environment — Nginx/PHP-FPM Alpine, MariaDB, Redis, Mailpit, Swagger UI
3. **Phase 3 (complete):** Core Composer packages — lorisleiva/laravel-actions, spatie/laravel-data, PHPStan level max
4. **Phase 4 (complete):** HealthCheck domain — Actions/Data/Services/Enums pattern, 100% test coverage, Docker non-root user
5. **Phase 5 (in progress):** Cleanup, refactoring, and hardening

Long-term vision: team starter template and migration target for a legacy PHP application.

---

## Documentation Structure

```
docs/
├── agentic/
│   └── BUILD.md        # Living build log — append after each phase
└── architecture/       # ADRs, diagrams, design decisions (Phase 2+)
```

**Always append to `docs/agentic/BUILD.md`** after making significant changes. Never edit past entries — add new dated sections instead.

---

## Stack

- **Framework:** Laravel 13.x (PHP >= 8.3)
- **PHP:** 8.4-fpm-alpine (custom Docker image, multi-stage: base/dev/ci/release)
- **Database:** MariaDB 11 (Eloquent/MySQL-compatible)
- **Cache / Sessions / Queues:** Redis 7 (phpredis extension)
- **Mail:** Mailpit (local SMTP capture, web UI on :8026)
- **API Docs:** Swagger UI (:8081) — spec at `storage/api-docs/openapi.yaml`
- **Web Server:** Nginx Alpine → PHP-FPM on port 9000

---

## Essential Commands

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Build / rebuild PHP image
docker compose build app

# Artisan commands
docker compose exec app php artisan <command>

# Composer
docker compose exec app composer <command>

# First-run setup (after docker compose up -d)
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# Run tests
docker compose exec app php artisan test

# Tinker REPL
docker compose exec app php artisan tinker

# View logs
docker compose logs -f app
docker compose logs -f nginx
```

**Service URLs:**
| Service | URL |
|---------|-----|
| Laravel app | http://localhost:8000 |
| Mailpit UI | http://localhost:8026 |
| Swagger UI | http://localhost:8081 |
| MariaDB | localhost:3307 |
| Redis | localhost:6380 |

---

## Directory Structure

```
/work/laravel-prototype/
├── app/                  # Application code (Models, Controllers, Services)
├── bootstrap/            # Framework bootstrap files
├── config/               # Configuration files
├── database/             # Migrations, seeders, factories
├── docs/
│   ├── agentic/
│   │   └── BUILD.md      # Living build log — append after each phase
│   └── architecture/     # ADRs, diagrams (Phase 2+)
├── public/               # Web root (index.php)
├── resources/            # Views, lang files, raw assets
├── routes/               # Route definitions (web.php, api.php, console.php, api/{domain}.php)
├── storage/              # Logs, cache, compiled files
├── tests/                # Feature and Unit tests
├── .omc/                 # Agentic orchestration (specs, plans, state)
│   ├── specs/            # Deep-interview requirement specs
│   └── plans/            # Ralplan consensus plans
└── CLAUDE.md             # This file
```

---

## Conventions for Agentic Work

- **Before any change:** Read `docs/agentic/BUILD.md` for current architecture context
- **After any significant change:** Update `docs/agentic/BUILD.md` to reflect current state (git tracks history — no need to append dated entries)
- **All PHP commands:** Run via `docker compose exec app` (no native PHP on host)
- **New specs/plans:** Save to `.omc/specs/` and `.omc/plans/` respectively
- **Domain CLAUDE.md:** Every domain directory under `app/` gets a `CLAUDE.md` documenting: purpose, consumers, how to extend (e.g. add a service), auth model, and any non-obvious patterns
- **No AI attribution:** Never include `🤖 Generated with Claude Code` or any similar attribution text in git commit messages, PR descriptions, or GitHub issues

## Validation Gate

Before marking any implementation task complete, all three gates must pass:

```bash
# 1. Tests — must stay at 100% coverage
docker compose exec app php artisan test --coverage

# 2. PHPStan — must exit 0, empty baseline, no suppressions
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=-1

# 3. Pint — must produce no changes
docker compose exec app ./vendor/bin/pint --test
```

If coverage drops below 100%, write the missing tests before proceeding. If PHPStan fails, fix the type error — do not add to the baseline.

Once all three gates pass, regenerate the OpenAPI spec so Swagger UI reflects the current state:

```bash
docker compose exec app php artisan l5-swagger:generate
```

## Laravel Boost MCP

Configured in `mcp.json` at the project root — Claude Code loads it automatically. Use these tools instead of manual alternatives:

- `search-docs` — version-specific Laravel/package documentation (always run before coding)
- `database-query` — read-only DB queries without tinker
- `database-schema` — inspect table structure before writing migrations or models
- `get-absolute-url` — resolve correct URL before sharing with user
- `browser-logs` — read browser errors and exceptions

Server command: `docker compose exec -T app php artisan boost:mcp` (`cwd: "."` keeps the path portable)

---

_Last updated: 2026-04-21 (Phase 5 in progress — pre-Phase-6 refactor: routes restructure, config/api.php, ApiVersion middleware, ApplicationHealthCheck)_

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
