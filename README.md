# Media Buyers API Test Suite

PHP + Codeception automated tests for the **Media Buyers** REST API contract.

---

## Codeception Setup Overview

The suite uses [Codeception 5](https://codeception.com/) with three modules:

| Module | Role |
|---|---|
| **REST** | Sends HTTP requests, asserts status codes, JSON paths, and response bodies |
| **PhpBrowser** | HTTP client layer that REST depends on (no headless browser needed for API tests) |
| **Asserts** | Exposes PHPUnit-style assertions (`assertIsArray`, `assertGreaterThan`, etc.) inside Cest classes |

A custom **SchemaValidator** helper wraps `justinrainbow/json-schema` to validate responses against draft-07 JSON Schema files.

The base URL is read from the `BASE_URL` environment variable (`.env` file, never hard-coded).

---

## Repository Structure

```
├── codeception.yml                          # Codeception root config; reads .env params
├── composer.json                            # Dependencies
├── .env.example                             # Template — copy to .env and set BASE_URL
│
└── tests/
    ├── api.suite.yml                        # Suite config: modules, URL, helpers
    │
    ├── api/
    │   ├── GetMediaBuyersCest.php           # 7 tests — GET /api/mediabuyers (G1–G7)
    │   └── PostMediaBuyerCest.php           # 13 tests — POST /api/mediabuyers (P1–P11)
    │
    ├── schemas/
    │   ├── get-media-buyers-schema.json     # JSON Schema for GET response (provided in contract)
    │   └── post-media-buyer-schema.json     # JSON Schema for POST success response
    │
    └── _support/
        ├── Factory/
        │   └── MediaBuyerFactory.php        # Payload builder — no hard-coded JSON in tests
        └── Helper/
            └── SchemaValidator.php          # Custom Codeception helper for schema validation
```

---

## What Was Built

### Scenarios Selected

**GET /api/mediabuyers — G1 through G7 (7 tests)**

All seven criteria were automated because they cover orthogonal properties of the same endpoint: HTTP contract (G1), structural contract (G2, G4), edge-case collection contract (G3), and per-field data integrity (G5, G6, G7). None of these require server state setup, so they run cleanly from a fresh GET.

**POST /api/mediabuyers — P1 through P11 (13 tests)**

All eleven criteria were automated. The coverage splits into:
- Happy path: P1–P4 (valid create, id generation, field echo, active conversion)
- Missing required fields: P5 — parameterized over all four required fields
- Field validation: P6–P10 (email, initials, name boundary, mbId type, active type)
- Uniqueness: P11

What was **intentionally left out**: cross-field combinations (e.g. both email and name invalid at once) and RFC edge-case email addresses. These add breadth but not the critical-path coverage an initial suite needs. They are documented as future additions.

### Abstractions and What They Buy at Scale

**`MediaBuyerFactory`** — All payloads are constructed via `make()` / `without()` / `makeJson()`. When a field is added or renamed in the contract, only the factory changes; all 13 POST tests remain untouched. At 80 tests this becomes essential.

**`SchemaValidator` helper** — Schema validation is a single-line call in every test rather than 10 lines of validator setup. JSON Schema files are the contract source of truth; when the server team updates the response shape, updating the `.json` file propagates to all tests automatically.

**`@dataProvider` parameterization** — `testMissingRequiredFieldReturns400` and `testNameOutsideBoundaryReturns400` are data-driven. Adding a new required field or boundary case is one line in the provider array, not a new test method.

**`BASE_URL` from `.env`** — No URL appears in any test or suite file. Switching from local mock to staging to production is a one-line `.env` change.

### What Would Make This More Scalable

- **Data setup / teardown**: A `DELETE /api/mediabuyers/{id}` call in `afterEach` prevents uniqueness test failures from accumulating across runs.
- **Parallelization**: Codeception supports parallel group execution via `codecept run -g group1 -g group2 --parallel`.
- **CI integration**: Add a GitHub Actions workflow that spins up a mock server (Prism pointing at an OpenAPI spec), runs the suite, and publishes the Codeception HTML report as an artifact.
- **Schema versioning**: Store JSON Schema files alongside the OpenAPI spec in the backend repo and use a Git submodule or Prism to keep them in sync.
- **Contract testing**: Adopt Pact or Spectral for consumer-driven contract tests — the suite here covers the consumer side; Pact would add provider verification.

### Assumptions Where the Contract Is Silent

| Topic | Assumption |
|---|---|
| Duplicate `mbId` status code (P11) | Either 400 or 409 is accepted; the test asserts `in_array($code, [400, 409])` and documents why |
| `initials` in GET response | Always 2 uppercase letters as per the field description; the GET schema enforces `minLength: 2, maxLength: 2` |
| Empty collection (G3) | The suite documents the scenario but cannot execute it without DELETE or a seed-reset endpoint; the assertion is written as it would run once state control exists |
| `mbId` uniqueness scope | Assumed global (not per-tenant), since no tenant context is described in the contract |

---

## Running the Suite (once a real environment exists)

```bash
cp .env.example .env
# edit .env — set BASE_URL=https://your-api-host

composer install
vendor/bin/codecept run api
```
