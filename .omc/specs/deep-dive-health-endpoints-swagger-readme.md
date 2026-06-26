# Spec: Health Endpoint Documentation

**Interview ID:** dd-health-swagger-readme  
**Ambiguity at crystallization:** ~15%

---

## Goal

Add complete, human-facing documentation for the HealthCheck domain:
1. Create `app/HealthCheck/README.md` — the canonical domain README for operators/developers, covering HTTP API, CLI, Artisan, and curl usage including Bearer token instructions
2. Update the main `README.md` to reference the domain README, establishing the pattern for future domains
3. Fix the L5-Swagger config so `php artisan l5-swagger:generate` actually writes `openapi.yaml`, then generate the spec so Swagger UI at `:8081` shows the health endpoints

---

## Constraints

- Match existing code/doc style (Markdown tables, fenced code blocks with `#` annotations)
- Do NOT modify or remove the existing `app/HealthCheck/CLAUDE.md` — it serves a different audience (agentic context)
- All PHP commands run via `docker compose exec app`
- Do not add a request body or change response shape — document what exists
- Sanctum security scheme in `config/l5-swagger.php` is currently commented out — uncomment it so the "Authorize" button appears in Swagger UI
- Bearer token docs must work for local dev today (no login endpoint exists yet)

---

## Non-Goals

- Do NOT build a login/auth endpoint
- Do NOT document `meta` field per-service structure in OpenAPI (leave as opaque object for now)
- Do NOT create an `## API` section in the main README — domain READMEs replace that need
- Do NOT change route definitions, middleware, or response shapes
- Do NOT add a 422 response annotation (not a realistic response for these endpoints)

---

## Acceptance Criteria

1. `app/HealthCheck/README.md` exists and contains:
   - Authentication section explaining how to get a Sanctum Bearer token via Artisan tinker (local dev)
   - Endpoints table (`GET /api/health`, `GET /api/health/{service}`) with method, auth, and description
   - Artisan CLI section with `health:check` and `health:check {service}` examples
   - Curl section with `Authorization: Bearer $TOKEN` examples for both endpoints
   - Example responses for 200, 503, 401, 404
   - Note pointing to Swagger UI for interactive exploration

2. Main `README.md` `## Architecture` section updated to:
   - Add a "Domain READMEs" subsection or column linking to `app/HealthCheck/README.md`
   - Include a note that each domain has its own README for operational documentation

3. `config/l5-swagger.php`:
   - Sanctum security scheme uncommented (lines ~214-219)
   - `generate_always` remains `false` (appropriate)

4. `.env` and `.env.example`:
   - `L5_SWAGGER_GENERATE_YAML_COPY=true` added IF the discriminating probe confirms it's needed
   - OR confirmed that l5-swagger 11.x writes yaml natively when `format_to_use_for_docs=yaml`

5. `storage/api-docs/openapi.yaml` is a generated (non-placeholder) spec after running:
   ```bash
   docker compose exec app php artisan l5-swagger:generate
   ```

6. Swagger UI at `http://localhost:8081` shows:
   - `GET /api/health` and `GET /api/health/{service}` endpoints
   - "Authorize" button (from Sanctum scheme)
   - Correct 200/401/404/503 response codes

7. All three validation gates pass:
   ```bash
   docker compose exec app php artisan test --coverage --compact
   docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M
   docker compose exec app ./vendor/bin/pint --test
   ```

---

## Assumptions Exposed

- No login API endpoint exists in the prototype yet — Bearer token docs use tinker as the local dev method
- `app/HealthCheck/CLAUDE.md` serves agentic/developer context; the new `README.md` serves human operators
- L5-Swagger 11.x with swagger-php v4 scans PHP 8 attribute annotations (confirmed: `#[OA\Get]` syntax, not `@OA\` docblock)
- The `app/` directory is in the L5-Swagger scan path (confirmed: `config/l5-swagger.php` line 50)
- `GET /api/health` returns 200 when all services are `Ok`, 503 if any are `Degraded` or `Down`
- Valid `{service}` values are `app`, `mariadb`, `redis` (from `name()` methods on each checker)

---

## Technical Context

<trace-context>
### Existing Annotation State
- `app/HealthCheck/Actions/CheckServiceHealth.php`: PHP 8 `#[OA\Get]` annotations on both endpoints. Status codes 200/401/404/503 annotated. Sanctum security reference present. Path parameter enum `['app', 'mariadb', 'redis']` correct.
- `app/OpenApi/Schemas/HealthCheck/HealthStatusResourceSchema.php`: Schema for single-service response
- `app/OpenApi/Schemas/HealthCheck/HealthAggregateResourceSchema.php`: Schema for aggregate response
- `app/Http/Controllers/ApiController.php`: `#[OA\Info]`, `#[OA\Server]`, `#[OA\SecurityScheme(securityScheme: 'sanctum')]`

### L5-Swagger Config State
- Scan path: `base_path('app')` — correct
- Output: `storage/api-docs/openapi.yaml` — matches Docker volume mount
- `generate_yaml_copy`: `false` by default — may prevent `openapi.yaml` from being updated
- Sanctum security scheme: commented out at lines ~214-219
- `L5_SWAGGER_CONST_VERSION` and `L5_SWAGGER_CONST_HOST`: set via `.env`

### README State
- Main README: no health mentions; `## Architecture` table links to `app/HealthCheck/CLAUDE.md`
- `app/HealthCheck/CLAUDE.md`: exists for agentic context — do not modify
- Style: Markdown tables + fenced code blocks with `#` comments
</trace-context>

---

## Deliverables (Implementation Order)

1. **Run discriminating probe** first:
   ```bash
   docker compose exec app php artisan l5-swagger:generate && ls -la storage/api-docs/
   ```
   Check if `openapi.yaml` mtime updates. If not: add `L5_SWAGGER_GENERATE_YAML_COPY=true` to `.env` and `.env.example`.

2. **Uncomment Sanctum security scheme** in `config/l5-swagger.php` (lines ~214-219)

3. **Generate spec**: `docker compose exec app php artisan l5-swagger:generate`

4. **Create `app/HealthCheck/README.md`** with authentication, endpoints, Artisan CLI, curl examples, and example responses

5. **Update main `README.md`** `## Architecture` section to reference domain READMEs pattern

6. **Run validation gates** (tests, PHPStan, Pint)

---

## Trace Findings

- **Most likely explanation**: Health endpoints have complete PHP 8 attribute annotations. The spec was never generated. `generate_yaml_copy` config may be preventing `openapi.yaml` from updating. README gap is clean.
- **Lane 1 (OpenAPI completeness)**: Annotations exist and are structurally complete. `meta` field is opaque (intentional non-goal). Sanctum scheme commented out — fixable.
- **Lane 2 (README gap)**: Zero health mentions in README. Domain README pattern is the right architectural choice for a growing app.
- **Lane 3 (Swagger UI wiring)**: Primary blocker is `generate_yaml_copy=false` + spec never generated. Swagger UI container will reflect changes immediately once `openapi.yaml` is updated (volume mount, no caching).
