# Deep Interview Spec: Laravel Prototype — Phase 1

## Metadata
- Interview ID: laravel-proto-2026-04-20
- Rounds: 6
- Final Ambiguity Score: 21%
- Type: greenfield
- Generated: 2026-04-20
- Threshold: 20%
- Status: BELOW_THRESHOLD_EARLY_EXIT (21% — user confirmed Phase 1 scope sufficient)

## Clarity Breakdown
| Dimension | Score | Weight | Weighted |
|-----------|-------|--------|----------|
| Goal Clarity | 0.85 | 40% | 0.34 |
| Constraint Clarity | 0.80 | 30% | 0.24 |
| Success Criteria | 0.70 | 30% | 0.21 |
| **Total Clarity** | | | **0.79** |
| **Ambiguity** | | | **21%** |

## Goal
Agentically install a clean Laravel LTS application at `/work/laravel-prototype`, document the build process, and scaffold a `CLAUDE.md` for future agentic work on the project.

## Phase Context
This is **Phase 1 of 3**:
- **Phase 1 (this spec):** Clean Laravel LTS install + AGENTIC_BUILD.md + CLAUDE.md
- **Phase 2 (future):** Custom Docker environment (Nginx, Redis, Mailpit, MongoDB, OpenAPI/Swagger)
- **Phase 3 (future):** Additional Composer packages (auth, API docs, MongoDB driver, etc.)

The longer-term vision is a team template and eventual migration target for a legacy PHP application.

## Constraints
- Installation path: `/work/laravel-prototype`
- Laravel version: **latest LTS** (research online to confirm before installing)
- Composer packages: **Laravel defaults only** — no extras in Phase 1
- Docker: **not in scope** for Phase 1
- The agent must document every significant decision in `./docs/AGENTIC_BUILD.md`

## Non-Goals (Phase 1)
- No Docker setup
- No extra Composer packages (Sanctum, Horizon, MongoDB driver, etc.)
- No frontend scaffolding (Vite, Tailwind, etc.) beyond Laravel defaults
- No database configuration (beyond `.env` defaults)
- No CI/CD setup

## Acceptance Criteria
- [ ] Latest LTS Laravel version is identified from official docs before installation
- [ ] Laravel is installed at `/work/laravel-prototype` via `composer create-project`
- [ ] `php artisan --version` returns the expected LTS version
- [ ] Default Laravel directory structure is present and intact
- [ ] `/work/laravel-prototype/docs/AGENTIC_BUILD.md` exists and documents:
  - Laravel LTS version selected and why
  - Installation command(s) used
  - Any decisions made during setup
- [ ] `/work/laravel-prototype/CLAUDE.md` exists and includes:
  - Project purpose and phase context
  - Key commands (artisan, composer, test runner)
  - Conventions for future agentic work

## Assumptions Exposed & Resolved
| Assumption | Challenge | Resolution |
|------------|-----------|------------|
| "Automate" meant a script | What form does automation take? | Agentic workflow — agent researches docs and executes |
| Same architecture as legacy app | Is this a migration-compatible scaffold? | No — generic Laravel; migration compatibility is future scope |
| Many packages needed immediately | Which packages for Phase 1? | Laravel defaults only; packages deferred to Phase 3 |

## Technical Context
- Working directory: `/work/laravel-prototype`
- Spec directory: `/work/laravel-prototype/.omc/specs/`
- Agent should verify Laravel LTS version via https://laravel.com/docs before installing
- Use `composer create-project laravel/laravel:^{LTS_VERSION} .` inside the target directory

## Ontology (Key Entities)
| Entity | Type | Fields | Relationships |
|--------|------|--------|---------------|
| LaravelApp | core domain | version (LTS), path, structure | installed at /work/laravel-prototype |
| AgenticBuildDoc | supporting | installation steps, decisions, version rationale | lives inside LaravelApp at docs/ |
| ClaudeMd | supporting | commands, conventions, phase context | lives at LaravelApp root |
| ComposerPackages | external system | defaults only in Phase 1 | managed by LaravelApp |

## Interview Transcript
<details>
<summary>Full Q&A (6 rounds)</summary>

### Round 1
**Q:** What form should the automation take?
**A:** The Laravel application will be installed, documented and configured agentically based on online documentation and guides.
**Ambiguity:** 83%

### Round 2
**Q:** What is this Laravel prototype ultimately for?
**A:** PoC → promoted to team template → migration target for a legacy PHP ("symphony") application.
**Ambiguity:** 71%

### Round 3
**Q:** Which app is the "source symphony legacy PHP application"?
**A:** Focus on the generic Laravel app for now; migration architecture is different and future scope.
**Ambiguity:** 62%

### Round 4
**Q:** What services should the custom Docker environment include?
**A:** Nginx, Redis, Mailpit, OpenAPI, MongoDB.
**Ambiguity:** 56%

### Round 5
**Q:** Which Composer packages for Phase 1?
**A:** Start with Laravel defaults only. Add packages in a later phase.
**Ambiguity:** 32%

### Round 6
**Q:** How should the agent decide which Composer packages to install?
**A:** Start with Laravel defaults only — no extra packages in Phase 1.
**Ambiguity:** 21%

</details>
