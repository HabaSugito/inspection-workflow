# TS-003 — Progress an inspection: step times

| | |
|---|---|
| Design spec | [DS-003](ds-003.md), acceptance criteria AC-310 and AC-311 |
| Depends on | [TS-003 workflow](ts-003-inspection-workflow.md). Built in the same pull request |
| Files changed | 7 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). No deviations.

This spec was split from TS-003 workflow after the work was built, to keep each technical spec within 15 changed files. See DV-1.

## 1. Approach

Each step of the workflow records the time it happened, in a column of its own. The time is written by the same conditional update that changes the status, so a status and its time cannot disagree, and a refused step writes neither.

## 2. Tech stack & dependencies

Existing stack only. No new dependency.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `database/migrations/*_add_step_times_to_inspections_table.php` | New | Adds `assigned_at`, `started_at`, `completed_at` |
| 2 | `app/Models/Inspection.php` | Modified | Casts the three columns to datetimes |
| 3 | `app/Services/InspectionWorkflow.php` | Modified | Each step sets its own time in its update |
| 4 | `app/Http/Resources/InspectionResource.php` | Modified | Returns the three times |
| 5 | `database/factories/InspectionFactory.php` | Modified | The states `assigned()`, `inProgress()` and `completed()` set the times a real inspection would have |
| 6 | `tests/Feature/InspectionWorkflowTest.php` | Modified | Section 10 |
| 7 | `tests/Feature/ViewInspectionTest.php` | Modified | The exact-body test for AC-203 expects the three times |

Files 3 to 7 are also listed by TS-003 workflow, which changes other parts of them.

## 4. Data model

Added to `inspections`.

| Field name | Type | Required (Y/N) | Comments |
|---|---|---|---|
| `assigned_at` | timestamp | N | Set by `assign`. Null before |
| `started_at` | timestamp | N | Set by `start`. Null before |
| `completed_at` | timestamp | N | Set by `complete`. Null before |

## 5. State, persistence & execution mode

| Concern | Answer |
|---|---|
| Source of truth | The `inspections` table |
| Execution mode | Request/response |
| Idempotency | A time is written once. Repeating a step is refused, so its time is not rewritten |
| Concurrency | The time is part of the conditional update described in TS-003 workflow section 5. The request that loses a race writes no time |
| Failure handling | No retries |
| Backfill/migration | None. There is no existing data |

## 6. Interfaces

Fields added to the inspection resource:

```json
{
  "assigned_at": "2026-11-10T09:00:00Z",
  "started_at": null,
  "completed_at": null
}
```

Times are ISO 8601 in UTC, or null for a step that has not happened.

## 7. Flow, navigation & data movement

No UI in this spec.

1. `InspectionWorkflow` builds the update for a step: the new status, and the step's own column set to the current time.
2. The update runs only if the row still has the expected status.
3. The resource returns all three columns.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Validation | None. The times are never taken from a request |
| Invariants | A step writes its own time only, and never a time recorded by an earlier step. A refused step writes no time. A time in a request is ignored |
| Error handling | None of its own. See TS-003 workflow |
| User/system visibility | The three fields above |

## 9. Build, tooling & quality gates

`./start.sh` runs the tests with the rest.

| Area | Notes |
|---|---|
| Migrations | Adds three nullable columns. Rollback drops them, which a test checks |
| Config | None |
| Observability | None |
| Deployment | Out of scope |
| Performance | No extra query. The times are part of the update that already runs |

## 10. Testing strategy

All in `tests/Feature/InspectionWorkflowTest.php`.

| AC | Test |
|---|---|
| AC-310 | With the clock fixed at three different times, a full run through assign, start and complete records each time, and each earlier time is unchanged by later steps |
| AC-310 | An inspection created through `POST /api/inspections` has all three times null |
| AC-310 | A successful step sets `updated_at` to the time of the step and leaves `created_at` unchanged |
| AC-311 | For each of the 9 invalid pairs of status and action, the clock is moved forward one hour before the refused request, and the whole stored row, including the three times and `updated_at`, equals the row from before |
| — | A `completed_at` sent in an action request is ignored |
| — | The factory states produce step times in order: assigned, then started, then completed |
| — | Rolling the migration back removes the three columns and leaves `status` |

Checked once by hand, not kept as tests: three deliberate bugs were introduced and each failed a test. They were not recording `assigned_at`, not recording `started_at`, and overwriting `started_at` on complete.

Not tested: nothing in this spec is left untested.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | One column per step | A separate table of status changes | Three fixed steps. A table would be an audit trail, which needs to know who acted, and there is no authentication (TS-003 workflow DF-2) |
| D-2 | The columns are added by a second migration | Editing the migration from TS-001 | Each technical spec stays a self-contained change |
| D-3 | The time is written in the same statement as the status | A second update | A status and its time cannot disagree, and the request that loses a race writes nothing |

### Deviations from this spec

| ID | Deviation | Reason |
|---|---|---|
| DV-1 | This spec was written after the code, by splitting TS-003 workflow | That spec had grown to 16 changed files, past the limit of 15 in `AGENTS.md`. A review of the pull request found it. The content here was reviewed as part of TS-003 workflow before the split, and the decision to record step times is OQ-301 in DS-003 |

### Open questions

None.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Who performed each step | Needs authentication first |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-1 | The times are the server's clock in UTC. The UI shows them in the browser's time zone | Stated in the UI. See TS-003 UI |
