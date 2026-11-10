# TS-003 — Progress an inspection: workflow and API

| | |
|---|---|
| Design spec | [DS-003](ds-003.md), acceptance criteria AC-301 to AC-311 |
| Depends on | [TS-001](../SPEC-0001/ts-001-create-inspection.md), [TS-002 API](../SPEC-0002/ts-002-view-inspections-api.md) |
| Files changed | 15 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). No deviations.

## 1. Approach

The order of the statuses is defined once, in the `InspectionStatus` enum. One service, `InspectionWorkflow`, applies it and is the only code that changes an inspection's status. Each step is its own endpoint and is written to the database as a conditional update, so a step can only succeed from the status it expects. The resource gains `next_action`, so that the UI does not repeat the rules.

## 2. Tech stack & dependencies

Existing stack only. No new dependency. In particular, no state-machine package is added.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `database/migrations/*_add_step_times_to_inspections_table.php` | New | Adds `assigned_at`, `started_at`, `completed_at` |
| 2 | `app/Enums/InspectionStatus.php` | Modified | Adds `next()`, `canTransitionTo()`, `nextAction()` |
| 3 | `app/Models/Inspection.php` | Modified | Casts the three new columns to datetimes |
| 4 | `database/factories/InspectionFactory.php` | Modified | Adds states `assigned()`, `inProgress()`, `completed()` |
| 5 | `app/Services/InspectionWorkflow.php` | New | `assign`, `start`, `complete` |
| 6 | `app/Exceptions/InvalidTransition.php` | New | Thrown when a step is refused. Carries the current status and a message |
| 7 | `app/Http/Requests/AssignInspectionRequest.php` | New | Validation for assign |
| 8 | `app/Http/Controllers/InspectionTransitionController.php` | New | `assign`, `start`, `complete`. Each calls the service and returns the resource |
| 9 | `app/Http/Resources/InspectionResource.php` | Modified | Adds `next_action` and the three times |
| 10 | `routes/api.php` | Modified | Adds the three `POST` routes |
| 11 | `bootstrap/app.php` | Modified | Renders `InvalidTransition` as a 409 |
| 12 | `database/seeders/DatabaseSeeder.php` | Modified | Sample inspections in every status |
| 13 | `tests/Unit/InspectionStatusTest.php` | New | Section 10 |
| 14 | `tests/Feature/InspectionWorkflowTest.php` | New | Section 10 |
| 15 | `tests/Feature/ViewInspectionTest.php` | Modified | The exact-body test for AC-203 expects the four new resource fields (see DV-1) |

## 4. Data model

Added to `inspections`.

| Field name | Type | Required (Y/N) | Comments |
|---|---|---|---|
| `assigned_at` | timestamp | N | Set by `assign`. Null before |
| `started_at` | timestamp | N | Set by `start`. Null before |
| `completed_at` | timestamp | N | Set by `complete`. Null before |

Rules on the enum:

| Status | `next()` | `nextAction()` |
|---|---|---|
| `pending` | `assigned` | `assign` |
| `assigned` | `in_progress` | `start` |
| `in_progress` | `completed` | `complete` |
| `completed` | `null` | `null` |

`canTransitionTo($to)` is true only when `$to` is `next()`.

## 5. State, persistence & execution mode

| Concern | Answer |
|---|---|
| Source of truth | The `inspections` table |
| Execution mode | Request/response |
| Idempotency | Repeating a step returns 409 and changes nothing |
| Concurrency | Each step is one conditional update: `UPDATE inspections SET ... WHERE id = ? AND status = <expected>`. If no row is updated, the step is refused. Two simultaneous requests for the same step cannot both succeed |
| Failure handling | No retries. The error is returned to the caller |
| Backfill/migration | None. There is no existing data |

## 6. Interfaces

| Interface | Type | Contract |
|---|---|---|
| `POST /api/inspections/{inspection}/assign` | HTTP API | Body: `inspector_name`. `pending` → `assigned`. Returns 200 and the resource |
| `POST /api/inspections/{inspection}/start` | HTTP API | No body. `assigned` → `in_progress`. Returns 200 and the resource |
| `POST /api/inspections/{inspection}/complete` | HTTP API | No body. `in_progress` → `completed`. Returns 200 and the resource |
| `InspectionWorkflow::assign(Inspection, string): Inspection` | PHP service | Throws `InvalidTransition` if refused |
| `InspectionWorkflow::start(Inspection): Inspection` | PHP service | Throws `InvalidTransition` if refused or if no inspector is set |
| `InspectionWorkflow::complete(Inspection): Inspection` | PHP service | Throws `InvalidTransition` if refused |
| `InvalidTransition` | PHP exception | Public `currentStatus` and `allowedAction` |

Fields added to the inspection resource:

```json
{
  "next_action": "start",
  "assigned_at": "2026-11-10T09:00:00Z",
  "started_at": null,
  "completed_at": null
}
```

`next_action` is `assign`, `start`, `complete` or `null`. Times are ISO 8601 in UTC, or null.

409 body:

```json
{
  "message": "This inspection is pending. The only action allowed is: assign.",
  "current_status": "pending",
  "allowed_action": "assign"
}
```

`allowed_action` is null for a completed inspection, and for an assigned inspection that has no inspector (AC-306), because no action can proceed. The message differs in those two cases:

| Case | `message` |
|---|---|
| Completed | `This inspection is completed. No further action is allowed.` |
| Assigned with no inspector, on `start` | `This inspection has no inspector, so it cannot be started.` |

In a message, `in_progress` is written `in progress`.

A 404 for an unknown ID uses the handling from TS-002 API.

## 7. Flow, navigation & data movement

No UI in this spec.

1. The client sends `POST /api/inspections/{inspection}/{action}`.
2. Route model binding loads the inspection, or the request ends with 404.
3. For `assign`, `AssignInspectionRequest` validates `inspector_name`, or the request ends with 422.
4. `InspectionTransitionController` calls the matching method on `InspectionWorkflow`.
5. The service checks `canTransitionTo` on the status it was given. `start` also checks that an inspector is set. If either check fails it throws `InvalidTransition`.
6. The service runs the conditional update, setting the new status, the step's time and, for `assign`, the inspector name.
7. If the update changed no row, another request got there first. The service reloads the inspection and throws `InvalidTransition` with its actual status.
8. Otherwise the service reloads the inspection and returns it. The controller returns the resource.
9. `bootstrap/app.php` renders any `InvalidTransition` as the 409 body.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Validation | `inspector_name` (assign only): required, string, max 255. A blank value fails `required` because Laravel trims it and turns it into null first |
| Invariants | Status only changes to `next()`. Only `InspectionWorkflow` writes `status`, `inspector_name` and the three times. A step's time is written once, in the same update as the status. A refused step writes nothing |
| Error handling | 422 for a missing or over-long inspector name. 409 for a step refused in the current status. 404 for an unknown ID. Validation runs before the status check, so `assign` with no name on a completed inspection returns 422 |
| User/system visibility | The bodies in section 6 |

409 is used for a refused step because the request is well formed and conflicts with the inspection's state. 422 is kept for input that is wrong in itself.

## 9. Build, tooling & quality gates

`./start.sh` runs the new tests with the rest.

| Area | Notes |
|---|---|
| Migrations | Adds three nullable columns. Rollback drops them |
| Config | None |
| Observability | None beyond Laravel's default log |
| Deployment | Out of scope |
| Performance | Each step is one select (route binding), one update and one reload |

The seeder is extended to create inspections in each of the four statuses through the factory states, so that every UI control can be seen.

## 10. Testing strategy

Each test is written before the code it covers, run, and seen to fail for the reason it is meant to catch. Each test name starts with its AC ID. Every test that expects a refusal also asserts that the stored row is unchanged, and every test that expects success asserts the stored row as well as the response.

`tests/Unit/InspectionStatusTest.php`:

| AC | Test |
|---|---|
| AC-307 | Data provider over all 16 ordered pairs of statuses. `canTransitionTo` is true for exactly the 3 pairs in the workflow table |
| AC-307 | `next()` and `nextAction()` return the value in section 4 for each status |

`tests/Feature/InspectionWorkflowTest.php`:

| AC | Test |
|---|---|
| AC-301 | Assigning a pending inspection returns 200 with status `assigned`, the inspector name and `next_action: start` |
| AC-302 | Data provider: `inspector_name` missing, empty, and spaces only each return 422. A 256-character name returns 422 |
| AC-302 | `assign` with no name on a completed inspection returns 422, not 409: validation runs before the status check |
| AC-303 | Starting an assigned inspection returns 200 with status `in_progress` and `next_action: complete` |
| AC-304 | Completing an in-progress inspection returns 200 with status `completed` and `next_action: null` |
| AC-305 | Data provider over the 9 invalid pairs of status and action (4 statuses × 3 actions, minus the 3 valid ones). Each returns 409 with the current status and the allowed action. A separate test asserts that the provider has 9 pairs |
| AC-306 | An inspection with status `assigned` and a null inspector is refused on `start` with 409 and `allowed_action: null` |
| AC-308 | The 409 body has `message`, `current_status` and `allowed_action` with the expected values for a pending inspection asked to `start` |
| AC-308 | A completed inspection asked to `complete` returns the "No further action" message and `allowed_action: null` |
| AC-309 | Each of the three actions on an unknown ID returns the 404 body |
| AC-310 | With the clock fixed at three different times, a full run through assign, start and complete records each time, and each earlier time is unchanged by later steps |
| AC-310 | An inspection created through `POST /api/inspections` has all three times null and `next_action: assign` |
| AC-311 | In the AC-305 test the clock is moved forward one hour before the refused request, and the whole stored row, including the three times and `updated_at`, equals the row from before |
| Section 5 | Two model instances are loaded for the same pending row. One is assigned through the service. Assigning the other, stale instance throws `InvalidTransition`, and the row still holds the first inspector's name |

Checked once by hand, not kept as tests: two deliberate bugs were introduced to confirm that the tests catch them. Removing the status condition from the update failed the stale-instance test. Letting `canTransitionTo` allow a step back to `pending` failed five unit tests.

Not tested: two requests arriving at the same instant. The stale-instance test proves the same mechanism, the conditional update, without needing parallel processes.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | One endpoint per action | One `PATCH` that sets `status` | Each action has its own input. The client states intent and cannot ask for an arbitrary status |
| D-2 | Rules in an enum plus one service class | Methods on the model, or a state-machine package | Three transitions do not justify a package. One class is the single place that writes status |
| D-3 | The API returns `next_action` | The UI derives the next step from `status` | The rules stay in one place |
| D-4 | A step is a conditional update | A row lock in a transaction, or no protection | A few lines, works on SQLite, and can be tested with a stale model |
| D-5 | 409 for a refused step | 422 | Separates "the input is wrong" from "the state does not allow it" |
| D-6 | Inspector stored as a name | An `inspectors` table with a foreign key | The brief allows a name. A table adds CRUD that no story asks for |
| D-7 | Step times added by a second migration | Editing the migration from TS-001 | Each technical spec stays a self-contained change |
| D-8 | `InvalidTransition` holds no HTTP knowledge. The 409 is built in `bootstrap/app.php` | A `render()` method on the exception | The service layer stays free of HTTP, as the architecture requires |

### Deviations from this spec

| ID | Deviation | Reason |
|---|---|---|
| DV-1 | `tests/Feature/ViewInspectionTest.php` was changed, which this spec did not first list | Its AC-203 test compares the whole response body. The resource now has four more fields, so the expected body changed with the intended behaviour. No assertion was loosened |
| DV-2 | `bootstrap/app.php` also marks `InvalidTransition` as not reported | A refused step is an expected outcome. Without this, each 409 would be written to the error log |
| DV-3 | The service reloads the model with `refresh()` before it throws for a lost race, and `InvalidTransition` is built through two named constructors | Keeps the three messages in one place |

### Open questions

None.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Authentication and per-role permissions for each action | Out of scope in the brief |
| DF-2 | Audit trail of who performed each step | Needs authentication first |
| DF-3 | Inspectors as records, and reassignment | Not in any story |
| DF-4 | Cancelling an inspection | Not in any story |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-1 | Any caller can perform any step | Stated in DS-003 A-302. See DF-1 |
| R-2 | A row written outside the service could be `assigned` with no inspector | `start` refuses it (AC-306). Only the service writes these columns |

### Verified against the skeleton

Checked in the scaffolded project (Laravel 13.40) before implementation.

| Premise | Where it was checked |
|---|---|
| An update through the Eloquent query builder also sets `updated_at` | `Eloquent\Builder::update` |
| A render callback registered in `bootstrap/app.php` can turn an exception class into a response | `Handler::render`, `Handler::renderViaCallbacks` |
