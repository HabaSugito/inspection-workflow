# TS-001 — Create an inspection

| | |
|---|---|
| Design spec | [DS-001](ds-001.md) |
| Depends on | The project baseline (see [docs/architecture.md](../../docs/architecture.md)) |
| Files changed | 10 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). No deviations.

## 1. Approach

An `inspections` table and its Eloquent model are introduced, with one endpoint, `POST /api/inspections`, to create a row. Input is validated by a form request, and the model upper-cases the vehicle registration on every write. This spec also registers the API routes file, which the later specs add to.

## 2. Tech stack & dependencies

Existing stack only, as installed by the Laravel skeleton: PHP 8.5, Laravel 13.40, PHPUnit 12 and SQLite. No new dependency.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `database/migrations/*_create_inspections_table.php` | New | Table definition (section 4) |
| 2 | `app/Enums/InspectionStatus.php` | New | The four status values |
| 3 | `app/Models/Inspection.php` | New | Model, casts, default status, upper-casing of the registration |
| 4 | `database/factories/InspectionFactory.php` | New | Valid inspections for tests |
| 5 | `app/Http/Requests/StoreInspectionRequest.php` | New | Validation rules for create |
| 6 | `app/Http/Resources/InspectionResource.php` | New | JSON shape of an inspection |
| 7 | `app/Http/Controllers/InspectionController.php` | New | `store` |
| 8 | `routes/api.php` | New | `POST /api/inspections` |
| 9 | `bootstrap/app.php` | Modified | Registers `routes/api.php` through the `api:` argument of `withRouting`, which applies the `/api` prefix |
| 10 | `tests/Feature/CreateInspectionTest.php` | New | Section 10 |

## 4. Data model

Table `inspections`.

| Field name | Type | Required (Y/N) | Comments |
|---|---|---|---|
| `id` | integer | Y | Primary key, auto-increment |
| `customer_name` | string(255) | Y | |
| `vehicle_registration` | string(20) | Y | Stored in upper case |
| `requested_date` | date | Y | |
| `notes` | text | N | Up to 2000 characters |
| `status` | string | Y | One of `pending`, `assigned`, `in_progress`, `completed`. Database default `pending`. Cast to `InspectionStatus` |
| `inspector_name` | string(255) | N | Null when created |
| `created_at`, `updated_at` | timestamp | Y | Set by Laravel |

`status` is a string column, not a database enum, so a later change to the set of statuses needs no schema change. The enum cast rejects an unknown value when a row is read.

## 5. State, persistence & execution mode

| Concern | Answer |
|---|---|
| Source of truth | The `inspections` table |
| Execution mode | Request/response |
| Idempotency | None. Sending the same request twice creates two inspections (DS-001 A-103) |
| Concurrency | Not applicable. Each request inserts its own row |
| Failure handling | No retries. The error is returned to the caller |
| Backfill/migration | None. The table is new |

## 6. Interfaces

| Interface | Type | Contract |
|---|---|---|
| `POST /api/inspections` | HTTP API | Body: `customer_name`, `vehicle_registration`, `requested_date`, `notes` (optional). Returns 201 and the inspection resource, or 422 |

Inspection resource:

```json
{
  "data": {
    "id": 1,
    "customer_name": "Jane Smith",
    "vehicle_registration": "AB12 CDE",
    "requested_date": "2026-12-01",
    "notes": null,
    "status": "pending",
    "inspector_name": null,
    "created_at": "2026-11-10T09:00:00Z",
    "updated_at": "2026-11-10T09:00:00Z"
  }
}
```

`requested_date` is `YYYY-MM-DD`. Timestamps are ISO 8601 in UTC.

422 body:

```json
{
  "message": "The customer name field is required.",
  "errors": { "customer_name": ["The customer name field is required."] }
}
```

## 7. Flow, navigation & data movement

No UI in this spec.

1. The client sends `POST /api/inspections`.
2. `StoreInspectionRequest` validates the body. On failure Laravel returns 422 and nothing is stored.
3. `InspectionController::store` creates an `Inspection` from the validated fields only.
4. The model upper-cases `vehicle_registration`. `status` takes its default, `pending`.
5. The controller returns the `InspectionResource`. Laravel answers 201 for a resource whose model was just created.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Validation | `customer_name`: required, string, max 255. `vehicle_registration`: required, string, max 20. `requested_date`: required, `date_format:Y-m-d`, `after_or_equal:today`. `notes`: nullable, string, max 2000. Laravel trims input and turns empty strings into null before validation, so a blank value fails `required` |
| Invariants | A new inspection is `pending` with no inspector. `vehicle_registration` is upper case in the database, whichever code path wrote it |
| Error handling | 422 for invalid input. The caller should correct the request, not retry it unchanged |
| User/system visibility | The 422 body above. Under `api/*` the response is JSON even when the request has no `Accept: application/json` header. The skeleton's `bootstrap/app.php` already configures this, and AC-106 pins it |

How the invariants are enforced:

- The model's `$fillable` lists only the four input fields, and the controller passes only validated data. A `status` or `inspector_name` in the request therefore never reaches the model (AC-107).
- Upper-casing is an attribute mutator on the model, using `mb_strtoupper`, so the factory, the seeder and the API all normalise the same way (AC-109).
- "Today" is `today()` in the application timezone, which the skeleton sets to UTC (DS-001 A-104).

## 9. Build, tooling & quality gates

`./start.sh` runs `php artisan test` and `vendor/bin/pint --test`. The new test file is under `tests/Feature`, so it is included.

| Area | Notes |
|---|---|
| Migrations | One new table. Rollback drops it |
| Config | None |
| Observability | None beyond Laravel's default log |
| Deployment | Out of scope |
| Performance | Not a concern |

## 10. Testing strategy

Each test is written before the code it covers, run, and seen to fail for the reason it is meant to catch. Each test name starts with its AC ID.

| AC | Test in `tests/Feature/CreateInspectionTest.php` |
|---|---|
| AC-101 | A valid request returns 201, the resource has status `pending` and a null inspector, and the row exists |
| AC-102 | A request without `notes` returns 201 with null notes |
| AC-103 | Data provider over the three required fields: each missing field returns 422 with an error under that key |
| AC-104 | Data provider over invalid dates (`not-a-date`, `2027-02-30`, `01/12/2026`): each returns 422 with an error under `requested_date` |
| AC-105 | Data provider over each field at one character above its limit: each returns 422. A value exactly at the limit is accepted |
| AC-106 | A 422 body has `message` and `errors`, and the table is still empty. One case sends the request without a JSON `Accept` header and still gets JSON |
| AC-107 | A request carrying `status: completed` and `inspector_name` returns 201 with status `pending` and a null inspector, and the row matches |
| AC-108 | With the clock fixed, yesterday's date returns 422 and today's date returns 201 |
| AC-109 | A lower-case registration is returned in upper case, and the stored value is compared byte for byte so that a case-insensitive comparison cannot hide a failure |

Not tested: `InspectionFactory`. No test in this spec uses it (see DF-3). Everything else in this spec is covered.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | Upper-casing lives in a model mutator | In the form request or the controller | Every writer of the column then normalises it identically |
| D-2 | `status` is a string column with a PHP enum cast | A database enum column | No schema change when statuses change, and SQLite has no native enum |
| D-3 | The skeleton's rule that all `api/*` errors render as JSON is kept and pinned by a test | Leave it untested | Later specs rely on it, and an API should not answer with an HTML error page |

### Verified against the skeleton

Checked in the scaffolded project (Laravel 13.40) before implementation.

| Premise | Where it was checked |
|---|---|
| The skeleton has no `routes/api.php`, and `withRouting` accepts an `api:` argument | `routes/`, `ApplicationBuilder::withRouting` |
| `api/*` requests get JSON errors, including validation errors | `bootstrap/app.php` (`shouldRenderJsonWhen`), `Handler::shouldReturnJson` |
| Input is trimmed and empty strings become null before validation | Global middleware in `Foundation\Configuration\Middleware` |
| A resource for a newly created model answers 201 | `ResourceResponse` |
| The application timezone is UTC | `config/app.php` |
| Tests run against in-memory SQLite | `phpunit.xml` |

### Deviations from this spec

| ID | Deviation | Reason |
|---|---|---|
| DV-1 | The AC-104 test uses `2027-02-30` where this spec first said `2026-02-30` | With the clock fixed at 2026-11-10, `2026-02-30` is also in the past, so the test could pass through the past-date rule (AC-108) without proving that an impossible date is rejected |

### Open questions

None.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Protection against duplicate requests (idempotency key) | Not in any story |
| DF-2 | Format check for vehicle registrations | Not in any story. Formats differ by country |
| DF-3 | An automated test that uses `InspectionFactory` | The tests in this spec create inspections through the API, so none of them uses the factory. It was run once by hand and produced a valid inspection. TS-002 API is the first spec whose tests use it |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-1 | A request made just after midnight UTC may be judged against a different "today" than the user's local date | Accepted for the exercise. A real system would take the user's or the site's timezone |
