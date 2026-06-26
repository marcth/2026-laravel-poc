# Plan: Laravel Prototype — Phase 1

## ADR
- **Decision:** Use `composer` Docker image to bootstrap Laravel 12 LTS into `/work/laravel-prototype`
- **Drivers:** No native PHP/Composer; Docker 29.4.0 available; Laravel 12 is current LTS
- **Alternatives considered:** Laravel Installer via Docker (more steps, same dependency), native install (not possible)
- **Why chosen:** Single `docker run --rm` command, official image, no permanent Docker configuration needed in Phase 1
- **Consequences:** Phase 2 Docker setup will complement (not conflict with) Phase 1 bootstrap
- **Follow-ups:** Phase 2 adds Nginx/Redis/Mailpit/MongoDB/OpenAPI services; Phase 3 adds Composer packages

## RALPLAN-DR Summary
- **Principles:** Agentic-first, LTS stability, minimal footprint, future-proof scaffolding, host-agnostic execution
- **Decision Drivers:** No native PHP/Composer, Laravel 12 LTS, target path `/work/laravel-prototype`
- **Chosen Option:** Docker `composer` image one-liner (Option A)

## Acceptance Criteria (from spec)
- [ ] Laravel 12 LTS identified from official docs before installation
- [ ] Laravel installed at `/work/laravel-prototype` via `composer create-project`
- [ ] `php artisan --version` returns Laravel 12.x
- [ ] Default Laravel directory structure is present and intact
- [ ] `/work/laravel-prototype/docs/AGENTIC_BUILD.md` exists and documents: version rationale, install command, decisions
- [ ] `/work/laravel-prototype/CLAUDE.md` exists with: purpose, phase context, key commands, agentic conventions

## Implementation Steps

### Step 1 — Bootstrap Laravel 12 via Docker
Install to a temp directory first (preserving the existing `.omc/` content), then merge only Laravel application files:

```bash
# Install to temp dir (uses php:8.3-cli with Composer for consistency)
docker run --rm \
  -v /work:/work \
  -w /work \
  php:8.3-cli \
  sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer create-project laravel/laravel:^12.0 /work/laravel-prototype-tmp --prefer-dist --no-interaction"

# Merge: copy Laravel files into target, explicitly excluding .omc/
rsync -a --exclude='.omc' /work/laravel-prototype-tmp/ /work/laravel-prototype/

# Clean up temp dir
rm -rf /work/laravel-prototype-tmp

# Fix ownership (Docker runs as root; restore to host user)
sudo chown -R $(id -u):$(id -g) /work/laravel-prototype
```

- Single image (`php:8.3-cli`) used for both install and verification — eliminates PHP version drift
- `rsync --exclude='.omc'` explicitly preserves specs/plans
- `chown` restores file ownership after Docker root writes

### Step 2 — Verify Installation
```bash
docker run --rm \
  -v /work/laravel-prototype:/app \
  -w /app \
  php:8.3-cli \
  php artisan --version
```
Expected output: `Laravel Framework 12.x.x`
Uses the same `php:8.3-cli` image as Step 1 for consistency.

### Step 3 — Create docs/AGENTIC_BUILD.md
Create `/work/laravel-prototype/docs/AGENTIC_BUILD.md` documenting:
- Laravel LTS version selected (12.x) and rationale (official LTS until Feb 2027)
- PHP version used (8.3 via Docker)
- Installation command used
- Decision: Docker bootstrap because no native PHP/Composer
- Phase context (Phase 1 of 3)
- Links to official docs consulted

### Step 4 — Create CLAUDE.md
Create `/work/laravel-prototype/CLAUDE.md` with:
- Project purpose and 3-phase roadmap
- Key commands (using Docker since no native PHP)
- Directory structure overview
- Conventions for future agentic work
- Pointer to `docs/AGENTIC_BUILD.md` for build history

## Risk Flags
- **`.omc/` preservation:** Must not overwrite existing specs/plans during install — handled by temp-dir approach
- **Docker image pull time:** `composer:latest` and `php:8.3-cli` may need pulling — expected first-run latency
- **File ownership:** Docker-created files may be owned by root — may need `chown` after install
