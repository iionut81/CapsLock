# Written Evaluation — Media Buyers API Automation

**Candidate:** Ionuț Iordache
**Date:** June 2026

---

## Q1 — Scenarios selected

I automated all 18 acceptance criteria across both endpoints (G1–G7 for GET, P1–P11 for POST), resulting in 20 active test methods plus one skipped test (G3b — empty collection — pending a state-reset endpoint).

Priority within the set: G1/P1 (status code + Content-Type) are the baseline — if these fail nothing else matters. G2/P1 schema validation give the highest single-assertion coverage. P5 is parameterized over all four required fields rather than written as four separate methods. P11 is the only test where I documented an assumption (400 vs. 409) because the contract explicitly leaves the status code open.

Intentionally left out: multi-field invalid combinations, RFC edge-case emails, pagination/filtering (not in the contract), and performance scenarios.

---

## Q2 — Abstractions and scale

**`MediaBuyerFactory`** — `make(overrides)`, `without(fields)`, `makeJson()`. No test contains a raw JSON literal. Adding a required field to the contract means one change in the factory defaults; all tests pick it up automatically.

**`SchemaValidator` helper** — schema validation is a one-line call (`$I->seeResponseMatchesJsonSchema($path)`). The JSON Schema files become the living contract — updating them propagates to all tests without rewriting assertions.

**`@dataProvider` parameterization** — P5 (missing required fields) and P8 (name boundary) are data-driven. Adding a new case is one line in the provider array, not a new method.

**`BASE_URL` from `.env`** — no URL is hard-coded. Switching from local to staging to CI requires changing one environment variable.

---

## Q3 — Contract-drift detection

1. **OpenAPI as source of truth** — generate JSON Schema files from `openapi.yml` on every backend PR via Spectral/Prism; consume them in the test repo via Git submodule.
2. **Schema-diff CI step** — `openapi-diff` on every backend merge to `main`; fails the pipeline on breaking changes without a version bump.
3. **Pact contract testing** — the consumer (this suite) publishes a pact file; the backend verifies it in its own CI. Drift breaks the backend pipeline before it reaches production.
4. **Nightly regression run** — failures not tied to recent test-repo changes indicate silent backend drift and trigger a Slack/Teams alert.
5. **Process** — QA as a required reviewer on any PR touching the API contract; a GitHub Action posts a before/after schema diff as a PR comment.

---

## Q4 — Tooling (including AI)

| Concern | Tool | Why |
|---|---|---|
| Test generation | Claude / GitHub Copilot | Generates first-pass Cest skeletons from an OpenAPI spec in seconds; human review still required for assertion quality |
| Contract validation | Spectral + Prism | Spectral lints the spec; Prism mocks the server for running tests without a live backend |
| Schema versioning | openapi-diff in CI | Detects breaking changes on every PR; blocks merge without a version bump |
| Flakiness detection | Codeception retry plugin + CI test history | Flags non-deterministic tests; AI log analysis clusters failures by root cause faster than manual triage |
| Reporting | Codeception HTML reporter + Allure | Per-test history, trend graphs, and CI artifact upload |

**On AI:** I use it for first-draft generation and framework lookups, not for assertion judgment — LLMs tend to assert what was sent rather than what should be enforced. The value is in velocity on boilerplate.

---

## Q5 — Most challenging situation

I was automating a multi-step form flow where a backend service synced user data asynchronously to a second system (FOS), and the UI picker in the next step pulled from that second system.

Tests passed 70% of the time and failed 30% — always on the picker-search step, always "no results found." The root cause was a race condition: the user created via the primary API was not yet visible in FOS when the picker made its first request, 2–3 seconds later.

I resolved it by writing a polling helper (`waitForAssignableHolder`) that retried the FOS API every 2 seconds until the newly created user appeared (60-second timeout), called it in `beforeEach` after user creation, and added a structured log line at each poll attempt to make the sync lag visible in CI — previously the failure looked like a selector issue, not a timing issue.

The lesson: async propagation between services is the most common source of E2E flakiness, and the fix is always to make the wait condition explicit and observable, not to add a `sleep()`.
