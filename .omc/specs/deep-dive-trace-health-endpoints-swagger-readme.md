# Deep Dive Trace: health-endpoints-swagger-readme

## Observed Result

Health endpoints (`GET /api/health`, `GET /api/health/{service}`) and the `health:check` Artisan command are not documented in Swagger UI or README.md. The `storage/api-docs/openapi.yaml` file is a 6-line hand-written placeholder with `paths: {}`.

## Ranked Hypotheses

| Rank | Hypothesis | Confidence | Evidence Strength | Why it leads |
|------|------------|------------|-------------------|--------------|
| 1 | PHP 8 attribute annotations exist and are mostly complete; the issue is that `openapi.yaml` has never been regenerated from them | High | Strong | Lane 1 confirmed both `#[OA\Get]` endpoint annotations, schemas, and Sanctum security scheme via direct file read. Lane 3 grep for `@OA\` (docblock style) found nothing — wrong syntax searched; confirms PHP 8 attribute annotations are not docblock style. |
| 2 | `generate_yaml_copy=false` config means running `l5-swagger:generate` writes `api-docs.json` but NOT `openapi.yaml`, leaving the Swagger UI container serving the stale placeholder | Medium | Moderate | `config/l5-swagger.php` has `generate_yaml_copy` defaulting to `false`; Docker container reads `SWAGGER_JSON=openapi.yaml`. These are in tension — one command would confirm. |
| 3 | README intentionally defers all API detail to Swagger UI | Low | Weak | No "see Swagger" redirect exists; CLI command isn't in Swagger at all; README documents specific Artisan commands already. |

## Evidence Summary by Hypothesis

- **Hypothesis 1**: `CheckServiceHealth.php` contains `#[OA\Get]` for both endpoints with status codes 200/401/404/503, Sanctum security reference, path parameter with enum `['app', 'mariadb', 'redis']`. `ApiController.php` has `#[OA\Info]`, `#[OA\Server]`, and `#[OA\SecurityScheme(securityScheme: 'sanctum')]`. Schema classes exist for `HealthStatusResource` and `HealthAggregateResource`.
- **Hypothesis 2**: `config/l5-swagger.php` line 248 has `generate_yaml_copy => false`. `.env` has no `L5_SWAGGER_GENERATE_YAML_COPY=true`. Primary output is `api-docs.json`; secondary yaml copy requires the flag. Swagger UI container is wired to `openapi.yaml`.
- **Hypothesis 3**: No evidence. README does not say "see Swagger." Testing section already references the `HealthCheck` test path directly.

## Evidence Against / Missing Evidence

- **Hypothesis 1**: `HealthStatusResourceSchema::meta` property is declared as `type: 'object', additionalProperties: OA\AdditionalProperties` — opaque, does not document the service-specific sub-fields (app/mariadb/redis each have distinct meta shapes). Not a validity gap but a usability gap.
- **Hypothesis 2**: Uncertain whether L5-Swagger 11.x always writes yaml when `format_to_use_for_docs=yaml`, regardless of `generate_yaml_copy`. One `ls -la storage/api-docs/` after generation would confirm.
- **Hypothesis 3**: No disconfirming evidence needed — ruled out by strong positive evidence for H1/H2.

## Per-Lane Critical Unknowns

- **Lane 1 (OpenAPI completeness)**: Whether `generate_yaml_copy=false` is the actual blocker or whether l5-swagger 11.x automatically writes yaml when `format_to_use_for_docs=yaml`. The annotations themselves are complete.
- **Lane 2 (README gap)**: Whether to add a new `## API` section or absorb health docs into existing sections (`## Common Commands` + `## Architecture` domain table). The README has no API section today.
- **Lane 3 (Swagger UI wiring)**: Same as Lane 1 — will `php artisan l5-swagger:generate` update `openapi.yaml` or only `api-docs.json`?

## Rebuttal Round

- **Best rebuttal to leader**: Even if all annotations are correct, without `generate_yaml_copy=true` (or equivalent), the `openapi.yaml` file served to Swagger UI is never updated after running `l5-swagger:generate`.
- **Why leader held**: This rebuttal is actually a refinement of H1, not a refutation. Both H1 and H2 are simultaneously true at different pipeline stages. The fix requires both: (a) verify/fix the yaml output config, (b) run generation, (c) update README.

## Convergence / Separation Notes

- Lane 1 and Lane 3 converge on `generate_yaml_copy` as the key config gap.
- Lane 1 and Lane 3's apparent contradiction about annotation presence (Lane 1 found `#[OA\...]`, Lane 3 grep returned empty) is explained by different search syntax: `#[OA\...]` PHP 8 attribute style vs `@OA\` PHPDoc style. No contradiction — annotations exist.
- Lane 2 is independent (README) and does not converge with the other lanes.

## Most Likely Explanation

The health endpoints are **already fully annotated** with PHP 8 attribute-style OpenAPI definitions. The Swagger UI shows nothing because:
1. The `openapi.yaml` has never been generated from the annotations
2. There may be a config issue (`generate_yaml_copy=false`) that prevents `openapi.yaml` from being written even after running `l5-swagger:generate`

The README gap is clean — no health documentation exists there at all, and it needs lightweight additions (endpoint reference + CLI command) matching the README's existing table/code-block style.

## Critical Unknown

Will `php artisan l5-swagger:generate` update `openapi.yaml` or only `api-docs.json`? Answer confirmed by: `docker compose exec app php artisan l5-swagger:generate && ls -la storage/api-docs/`. If `openapi.yaml` mtime doesn't update, set `L5_SWAGGER_GENERATE_YAML_COPY=true` in `.env`.

## Recommended Discriminating Probe

```bash
docker compose exec app php artisan l5-swagger:generate && ls -la storage/api-docs/
```
If `openapi.yaml` is not updated: add `L5_SWAGGER_GENERATE_YAML_COPY=true` to `.env` and re-run.
