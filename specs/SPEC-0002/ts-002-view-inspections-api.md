# TS-002 — View inspections: API

| | |
|---|---|
| Design spec | [DS-002](ds-002.md), stories US-201 and US-202 |
| Depends on | [TS-001](../SPEC-0001/ts-001-create-inspection.md) |
| Files changed | 5 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). No deviations.

## 1. Approach

Two read endpoints are added to the existing controller: a list and a single inspection. Both return the `InspectionResource` from TS-001 unchanged. An unknown ID is turned into a JSON 404 with a fixed message in one place, so that later endpoints taking an inspection ID behave the same way. The seeder gains sample inspections for the UI to show.

## 2. Tech stack & dependencies

Existing stack only. No new dependency.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `app/Http/Controllers/InspectionController.php` | Modified | Adds `index` and `show` |
| 2 | `routes/api.php` | Modified | Adds `GET /api/inspections` and `GET /api/inspections/{inspection}` |
| 3 | `bootstrap/app.php` | Modified | Renders a missing model under `api/*` as a 404 with a fixed message |
| 4 | `database/seeders/DatabaseSeeder.php` | Modified | Creates sample inspections, replacing the skeleton's sample user |
| 5 | `tests/Feature/ViewInspectionTest.php` | New | Section 10 |

## 4. Data model

No change. Uses the `inspections` table from TS-001.

## 5. State, persistence & execution mode

| Concern | Answer |
|---|---|
| Source of truth | The `inspections` table |
| Execution mode | Request/response, read only |
| Idempotency | Both endpoints are reads |
| Concurrency | Not applicable |
| Failure handling | No retries. The error is returned to the caller |
| Backfill/migration | None |

## 6. Interfaces

| Interface | Type | Contract |
|---|---|---|
| `GET /api/inspections` | HTTP API | Returns 200 and `{ "data": [ <inspection>, ... ] }`, ordered by `id` descending. Returns `{ "data": [] }` when there are none |
| `GET /api/inspections/{inspection}` | HTTP API | Returns 200 and `{ "data": <inspection> }`, or 404 |

`<inspection>` is the resource defined in TS-001 section 6.

404 body:

```json
{ "message": "Inspection not found." }
```

## 7. Flow, navigation & data movement

No UI in this spec.

- List: route → `InspectionController::index` → all inspections ordered by `id` descending → `InspectionResource::collection`.
- Single: route → route model binding resolves `{inspection}` → `InspectionController::show` → `InspectionResource`. If no row matches, Laravel throws before the controller runs. Laravel turns the missing-model exception into a `NotFoundHttpException` that keeps the original as its previous exception. A render callback in `bootstrap/app.php` matches that case under `api/*` and returns the 404 body.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Validation | None. Neither endpoint takes input other than the ID in the URL |
| Invariants | The list order is stable: `id` descending |
| Error handling | 404 for an ID that matches no inspection, including a non-numeric ID |
| User/system visibility | The 404 body above. The message is fixed so that it does not expose the model's class name |

## 9. Build, tooling & quality gates

`./start.sh` runs the new tests with the rest. It also runs `php artisan migrate:fresh --seed`, so the sample data is rebuilt on every run.

| Area | Notes |
|---|---|
| Migrations | None |
| Config | None |
| Observability | None beyond Laravel's default log |
| Deployment | Out of scope |
| Performance | The list is unpaginated (see R-1) |

The seeder creates a small fixed set of `pending` inspections through the factory. TS-003 extends it to cover every status.

## 10. Testing strategy

Each test is written before the code it covers, run, and seen to fail for the reason it is meant to catch. Each test name starts with its AC ID.

| AC | Test in `tests/Feature/ViewInspectionTest.php` |
|---|---|
| AC-201 | Three inspections are created in a known order. The list returns all three, and the IDs in the response are in descending order |
| AC-201 | A list item carries the customer name, vehicle registration and status that the UI shows |
| AC-202 | With an empty table, the list returns 200 and `data` is an empty array |
| AC-203 | Retrieving an existing ID returns 200 and every field of that inspection, with another inspection present to show the right one is chosen |
| AC-204 | An unknown numeric ID and a non-numeric ID each return 404 with exactly `{ "message": "Inspection not found." }`. One case sends no JSON `Accept` header |

Added after a review of the test coverage. Each pins behaviour that was already correct, so none was seen to fail first:

| AC | Test |
|---|---|
| AC-204 | The unknown-ID provider also covers `0`, `-1`, `1.5`, a number too large to be an ID, and text that looks like SQL. Each returns the 404 body |
| AC-204 | `PUT`, `PATCH` and `DELETE` on an inspection, and `DELETE` on the list, return a JSON 405 and change nothing. The API offers no way to edit or delete |
| AC-204 | An unknown path under `/api` returns a JSON 404 |

These tests create their inspections with `InspectionFactory`, which closes the deferred item DF-3 of TS-001.

Not tested: the seeder. It only calls the factory, and `start.sh` runs it on every execution, so a failure there stops the script.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | "Newest first" is ordering by `id` descending | Ordering by `created_at` | Rows created in the same second share a `created_at`, which would make the order unstable |
| D-2 | The 404 is rendered in `bootstrap/app.php` | A `missing()` handler on each route | One place covers every route that takes an inspection ID, including those added by TS-003 |
| D-3 | The 404 message is fixed text | Laravel's default message | The default includes the model's class name |

### Open questions

None.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Pagination, filtering and sorting | Not in any story |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-1 | The unpaginated list slows down as rows grow | Acceptable for sample data. See DF-1 |
| R-2 | An ID written as `01`, or with a leading space, resolves to inspection 1, because SQLite converts the text to a number | Accepted. No other inspection can be reached this way. Listed in the README as a known limitation |

### Verified against the skeleton

Checked in the scaffolded project (Laravel 13.40) before implementation.

| Premise | Where it was checked |
|---|---|
| A missing model becomes a `NotFoundHttpException` whose message names the model class, and render callbacks run after that conversion | `Handler::prepareException`, `Handler::render` |
