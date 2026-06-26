# Plan: Laravel Prototype — Phase 2 (Docker Environment)

## ADR
- **Decision:** Custom `php:8.3-fpm-alpine` image + docker-compose with Nginx, MariaDB, Redis, Mailpit, Swagger UI
- **Drivers:** phpredis requires PECL compilation; MariaDB is MySQL-compatible natively; Swagger UI needs a mounted spec location; Alpine reduces attack surface and image size (~100MB vs ~500MB)
- **Alternatives considered:** Debian-based image (rejected — larger attack surface, ~500MB image, no advantage for our extension set), predis Composer package (rejected — phpredis is Laravel 13 recommended)
- **Why chosen:** Alpine is more secure (smaller attack surface), significantly lighter, and all five required extensions compile cleanly with correct apk packages
- **Consequences:** Docker images will be ~500MB for PHP-FPM; first build will take 2-5 minutes
- **Follow-ups:** Phase 3 will add L5-Swagger/Scramble to generate `storage/api-docs/openapi.yaml` properly

## RALPLAN-DR Summary
- **Principles:** Single responsibility per container, credentials via .env, persistent volumes, Laravel convention-first, document in BUILD.md
- **Decision Drivers:** phpredis via PECL, MariaDB MySQL-compatible, Swagger UI needs placeholder spec, Alpine for security + size
- **Chosen Option:** php:8.3-fpm-alpine (smaller attack surface, ~100MB vs ~500MB, all extensions compile cleanly)

## Acceptance Criteria
- [ ] `Dockerfile` builds successfully from `php:8.3-fpm` with all 5 extensions installed
- [ ] `docker-compose.yml` defines all 6 services: app, nginx, mariadb, redis, mailpit, swagger-ui
- [ ] `docker/nginx/default.conf` routes PHP to PHP-FPM on port 9000, web root is `public/`
- [ ] `.env` updated with correct host/port values for all services
- [ ] `docker compose up -d` starts all services without errors
- [ ] `http://localhost` serves the Laravel welcome page
- [ ] `http://localhost:8025` serves Mailpit web UI
- [ ] `http://localhost:8080` serves Swagger UI
- [ ] MariaDB accessible at port 3306 with credentials from `.env`
- [ ] `php artisan migrate` runs successfully against MariaDB
- [ ] `php artisan tinker --execute 'echo Redis::ping();'` returns `+PONG`
- [ ] `docs/agentic/BUILD.md` updated with Phase 2 decisions
- [ ] `CLAUDE.md` updated with Docker Compose commands

## Files to Create / Modify

| File | Action |
|------|--------|
| `Dockerfile` | Create |
| `docker-compose.yml` | Create |
| `docker/nginx/default.conf` | Create |
| `.env` | Modify (update service hosts/ports) |
| `storage/api-docs/openapi.yaml` | Create (placeholder) |
| `docs/agentic/BUILD.md` | Append Phase 2 section |
| `CLAUDE.md` | Update commands section |

## Implementation Steps

### Step 1 — Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

# System dependencies (Alpine apk)
RUN apk add --no-cache \
    libzip-dev \
    icu-dev \
    icu-libs \
    oniguruma-dev \
    zip \
    unzip \
    curl \
    $PHPIZE_DEPS

# PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    bcmath \
    zip \
    mbstring

# phpredis via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html
```

> `$PHPIZE_DEPS` provides the build tools (gcc, autoconf, etc.) needed to compile PECL extensions. Removing them after install keeps the final image lean.

> App code is mounted as a volume — not COPY'd — for local dev hot-reloading.

### Step 2 — docker-compose.yml

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - .:/var/www/html
    networks:
      - laravel
    depends_on:
      - mariadb
      - redis

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - laravel
    depends_on:
      - app

  mariadb:
    image: mariadb:11
    environment:
      MARIADB_ROOT_PASSWORD: "${DB_ROOT_PASSWORD:-root}"
      MARIADB_DATABASE: "${DB_DATABASE:-laravel}"
      MARIADB_USER: "${DB_USERNAME:-laravel}"
      MARIADB_PASSWORD: "${DB_PASSWORD:-password}"
    volumes:
      - mariadb_data:/var/lib/mysql
    ports:
      - "3306:3306"
    networks:
      - laravel

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
    ports:
      - "6379:6379"
    networks:
      - laravel

  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"
      - "1025:1025"
    networks:
      - laravel

  swagger-ui:
    image: swaggerapi/swagger-ui
    ports:
      - "8080:8080"
    volumes:
      - ./storage/api-docs:/usr/share/nginx/html/api-docs
    environment:
      SWAGGER_JSON: /usr/share/nginx/html/api-docs/openapi.yaml
    networks:
      - laravel

networks:
  laravel:
    driver: bridge

volumes:
  mariadb_data:
  redis_data:
```

### Step 3 — docker/nginx/default.conf

```nginx
server {
    listen 80;
    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### Step 4 — .env updates

```env
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=password
DB_ROOT_PASSWORD=root

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@laravel-prototype.local"
MAIL_FROM_NAME="${APP_NAME}"
```

### Step 5 — Create storage/api-docs directory and placeholder spec

Create the directory **before** `docker compose up` — Swagger UI's volume mount will silently bind-mount an empty root-owned directory if it doesn't exist first:

```bash
mkdir -p storage/api-docs
```

Then create `storage/api-docs/openapi.yaml`:

### Step 5a — Placeholder OpenAPI spec

Create `storage/api-docs/openapi.yaml`:
```yaml
openapi: "3.1.0"
info:
  title: Laravel Prototype API
  version: "0.1.0"
  description: "Placeholder spec — generate with L5-Swagger or Scramble in Phase 3"
paths: {}
```

### Step 6 — Update CLAUDE.md Docker commands section

Replace Docker command examples with `docker compose` equivalents (Compose V2 syntax).

### Step 7 — Append to docs/agentic/BUILD.md

Document: all services, image versions, .env changes, phpredis compilation approach, Swagger UI placeholder strategy.

## Risk Flags
- **phpredis PECL compilation** may add 2-3 min to first `docker compose build`
- **Vendor mount exclusion** (`/var/www/html/vendor`) prevents host vendor from shadowing container vendor — important if host has no PHP
- **File ownership**: PHP-FPM runs as `www-data`; `storage/` and `bootstrap/cache/` must be writable
- **Swagger UI**: `storage/api-docs/` must exist before `docker compose up` or the volume mount will silently fail
