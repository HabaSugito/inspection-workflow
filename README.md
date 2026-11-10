# Inspection workflow

A small Laravel and React application for tracking vehicle inspections. It was built for a take-home exercise: "Deputy Lead Engineer, PHP / Laravel / React".

An inspection moves through four statuses, in this order only:

```
pending → assigned → in_progress → completed
```

An inspector must be assigned before an inspection can start.

## Where to look first

| If you want to see | Open |
|---|---|
| The workflow rules | `app/Enums/InspectionStatus.php` and `app/Services/InspectionWorkflow.php` |
| The tests for valid and invalid transitions | `tests/Unit/InspectionStatusTest.php` and `tests/Feature/InspectionWorkflowTest.php` |
| What was built, and why | `specs/`, one folder per feature. See [the next section](#how-it-was-built-spec-driven-development) |
| How the code is layered | `docs/architecture.md` |
| The rules this repository was built under | `AGENTS.md` |

## How it was built: spec-driven development

This project was built with spec-driven development (SDD). The specs were written and reviewed first, and the code was written from them. A feature goes through three steps, in this order:

1. **Design spec: what is built.** User stories, the acceptance criteria that follow from them, what is out of scope, and the assumptions. Anything that does not trace to a story is not built.
2. **Technical spec: how it is built.** The files to change, the data model, the interfaces, the validation and error handling, the tests, and the decisions with the alternatives that were considered.
3. **Code, tests first.** Each acceptance criterion becomes a named test before the code exists. The test is run and seen to fail, and then the code is written until it passes.

After the code is written, the spec is updated to match what was built. Where the code departs from the spec, the spec says so and gives the reason.

The history of the repository follows the same order. The specs were merged in their own pull request (#2) before any application code, and the technical specs were then built one pull request at a time (#4 to #8). The two specs for the workflow and its step times share one pull request.

### The `specs/` folder

One folder per design spec. It holds the design spec and the technical specs that implement it.

```
specs/
├── SPEC-0001/                             Create an inspection
│   ├── ds-001.md                          design spec
│   └── ts-001-create-inspection.md        technical spec
├── SPEC-0002/                             View inspections
│   ├── ds-002.md
│   ├── ts-002-view-inspections-api.md     the two read endpoints
│   └── ts-002-view-inspections-ui.md      the list and the detail panel
└── SPEC-0003/                             Progress an inspection
    ├── ds-003.md
    ├── ts-003-inspection-workflow.md      the three actions and their rules
    ├── ts-003-step-times.md               the time of each step
    └── ts-003-inspection-workflow-ui.md   the control for the next action
```

| File | Holds | Rule |
|---|---|---|
| `ds-NNN.md` | A design spec | At most about four user stories, so that it can be explained in two sentences |
| `ts-NNN-<name>.md` | A technical spec for design spec `NNN` | Changes at most 15 files. A design spec that needs more is split into several technical specs with different names |

Every technical spec has the same eleven sections, so a reader knows where to look:

| Section | Answers |
|---|---|
| 1. Approach | What is the shape of the solution, in a few sentences? |
| 2. Tech stack & dependencies | Is anything new added, and why could it not be avoided? |
| 3. Module / component breakdown | Which files change, and what is each one for? |
| 4. Data model | Which fields, of which types? |
| 5. State, persistence & execution mode | Where is the source of truth, and what happens with two requests at once? |
| 6. Interfaces | Which endpoints and function signatures, with which contracts? |
| 7. Flow, navigation & data movement | What happens, step by step? |
| 8. Validation, invariants & error handling | Which rules, and what does the caller see when one is broken? |
| 9. Build, tooling & quality gates | Which checks run, and are there migrations? |
| 10. Testing strategy | Which test covers which acceptance criterion, and what is not tested? |
| 11. Decisions, open questions, deferred items & risks | What was decided and why, what was left out, and where does the code depart from the spec? |

### How to trace a rule from the brief to a test

Acceptance criteria have IDs, and each test name starts with the ID it covers. For example, the brief says an inspector is required before an inspection moves to `in_progress`:

| Step | Where |
|---|---|
| Acceptance criterion AC-306 | `specs/SPEC-0003/ds-003.md` |
| How it is built and tested | `specs/SPEC-0003/ts-003-inspection-workflow.md`, sections 8 and 10 |
| The tests | `test_ac306_...` in `tests/Feature/InspectionWorkflowTest.php` |
| The code | `InspectionWorkflow::start()` in `app/Services/InspectionWorkflow.php` |

### Where the method was not followed

- `ts-003-step-times.md` was written after its code. The workflow spec had grown past 15 files, and it was split in two afterwards.
- Edge-case tests added by two later reviews were written after the code they cover. See [Known limitations](#known-limitations).
- The UI has no automated tests, so "tests first" does not apply to it. The UI specs list checks made by hand in their place.

Each of these is recorded in section 11 of the spec it concerns, under "Deviations from this spec".

## Setup

You need PHP 8.3 or later, Composer, and Node.js 22 with npm. It was developed on PHP 8.5.12, Composer 2.11 and Node.js 22.15. No database server is needed: the application uses SQLite.

```bash
./start.sh
```

This one script installs the PHP and JavaScript dependencies, creates `.env` if it is missing, rebuilds the SQLite database with sample data, and runs every check. It stops at the first failure and ends with `All checks passed.`

Then start the application:

```bash
php artisan serve
```

Open http://localhost:8000. The sample data has inspections in every status.

### With Docker

```bash
docker compose up --build
```

Open http://localhost:8000. The image is for local review only. It keeps the development dependencies, because the sample-data seeder needs them.

## Tests and checks

`./start.sh` runs all of these. Each can also be run alone.

| Check | Command |
|---|---|
| PHP tests (150) | `php artisan test` |
| PHP code style | `vendor/bin/pint --test` |
| TypeScript types | `npm run typecheck` |
| Frontend lint | `npm run lint` |
| Frontend formatting | `npm run format:check` |
| Frontend build | `npm run build` |

The tests use an in-memory SQLite database, so they do not touch the sample data.

There are no automated tests for the React UI. See [Known limitations](#known-limitations).

## API

All responses are JSON, including errors.

| Method and path | Does | Success | Errors |
|---|---|---|---|
| `POST /api/inspections` | Creates an inspection | 201 | 422 |
| `GET /api/inspections` | Lists inspections, newest first | 200 | |
| `GET /api/inspections/{id}` | Returns one inspection | 200 | 404 |
| `POST /api/inspections/{id}/assign` | `pending` → `assigned`. Needs `inspector_name` | 200 | 404, 409, 422 |
| `POST /api/inspections/{id}/start` | `assigned` → `in_progress` | 200 | 404, 409 |
| `POST /api/inspections/{id}/complete` | `in_progress` → `completed` | 200 | 404, 409 |

| Status code | Meaning |
|---|---|
| 422 | The input is wrong. The body lists the errors per field |
| 409 | The input is fine, but the inspection's status does not allow the action. Nothing is changed |
| 404 | No inspection has that ID |

Create an inspection:

```bash
curl -X POST http://localhost:8000/api/inspections \
  -H 'Content-Type: application/json' \
  -d '{"customer_name":"Jane Smith","vehicle_registration":"ab12 cde","requested_date":"2027-01-15","notes":"Morning visit"}'
```

```json
{
  "data": {
    "id": 8,
    "customer_name": "Jane Smith",
    "vehicle_registration": "AB12 CDE",
    "requested_date": "2027-01-15",
    "notes": "Morning visit",
    "status": "pending",
    "inspector_name": null,
    "next_action": "assign",
    "assigned_at": null,
    "started_at": null,
    "completed_at": null,
    "created_at": "2026-11-10T09:00:00Z",
    "updated_at": "2026-11-10T09:00:00Z"
  }
}
```

Try to start it before an inspector is assigned:

```bash
curl -X POST http://localhost:8000/api/inspections/8/start
```

```json
{
  "message": "This inspection is pending. The only action allowed is: assign.",
  "current_status": "pending",
  "allowed_action": "assign"
}
```

## Key decisions

Each technical spec records its decisions in section 11, with the alternative that was considered. These are the ones that shape the code most.

| Decision | Reason |
|---|---|
| One endpoint per action (`/assign`, `/start`, `/complete`), not one endpoint that sets a status | The client states what it wants to do and cannot ask for an arbitrary status. Each action has its own input |
| The order of the statuses is defined once, in the `InspectionStatus` enum. One class, `InspectionWorkflow`, is the only code that changes a status | One place to read the rules, and one place to change them |
| Each step is a conditional update: `UPDATE ... WHERE id = ? AND status = <expected>` | Two requests for the same step cannot both succeed. It needs no lock and works on SQLite |
| 409 for an action the status does not allow, 422 for input that is wrong in itself | A client can tell "fix your request" from "the inspection has moved on" |
| The API returns `next_action` | The UI shows the control for that action and holds no workflow rules of its own |
| No state-machine package | Three transitions do not justify a dependency |
| The inspector is stored as a name | The brief allows a name or an ID. A table of inspectors would add screens and endpoints that nothing asks for |
| API and UI in one Laravel project | One install and one command to run, with no CORS setup |
| Specs before code (spec-driven development) | Decisions are made and reviewed in a short document before they are made in code, where they cost more to change. See [How it was built](#how-it-was-built-spec-driven-development) |

## Assumptions

The brief builds on an earlier system-design exercise. I did not take part in one, so the design specs under `specs/` stand in for it.

- There is no authentication, as the brief says. Any caller can create an inspection and perform any step.
- A vehicle registration is free text of up to 20 characters. It is stored in upper case. No country's format is enforced.
- A requested date must be today or later, by the server's date in UTC.
- Sending the same create request twice creates two inspections.
- The time of each step (assigned, started, completed) is recorded. The brief does not ask for this. It was added because an inspection's history is not readable from its status alone.
- "Newest first" in the list means by ID, descending.

## Known limitations

- **No automated UI tests.** The UI is covered by the type check, the linter, the build, and checks made by hand in a browser. The steps and results are in section 10 of the two UI specs. Two paths in the UI were changed after review and not run: an unreadable response from the server, and a failed reload after a failed action.
- **Two requests at exactly the same instant are not tested.** A test with a stale copy of an inspection proves the mechanism that protects against it, the conditional update, without running parallel processes.
- **How an unusual ID is handled depends on SQLite.** `/api/inspections/01`, or an ID with a leading space, returns inspection 1, because SQLite converts the text to a number.
- **The requested date has no upper limit.** `9999-12-31` is accepted.
- **The 20-character limit on the registration is enforced for the API only.** SQLite does not enforce the column length, so code that writes without going through the API could store a longer value.
- **A row with an unknown status breaks the list.** The response fails with 500. Only the workflow service writes the status, and it writes valid values.
- **The list is not paginated.**
- **The API response is not checked against the TypeScript types at run time.**
- **With `APP_DEBUG=true`, the default for local use, a server error shown in the UI includes internal detail** such as SQL.
- **The pull request for the workflow changes 16 files.** Its technical spec passed this repository's own limit of 15 and was split in two after the code was written. Both specs say so.
- **Some tests were written after the code they cover.** The first tests for each feature were written first and seen to fail. Two later reviews added edge-case tests for behaviour that was already correct, so those could not be seen to fail first. Deliberate bugs were introduced instead, to check that the tests catch them. Each spec lists which tests were added later.

## With more time

1. Automated tests for the UI, starting with the action control and the error path.
2. Authentication, then a rule for who may perform each step, then a record of who did.
3. Pagination and filtering by status on the list.
4. A constraint on the ID in the route, to remove the dependence on SQLite.
5. Inspectors as records, with reassignment.
6. A continuous-integration job that runs `./start.sh` on every pull request. Today it is run by hand.
7. A way to cancel an inspection.

## AI tools

I used Claude Code (Anthropic) throughout: for the specs, the code, the tests, the reviews and this README. The commits it wrote carry a `Co-Authored-By` line.

What I decided myself:

- The method: design spec, then technical spec, then code, with tests written first.
- The structure of the specs and the limit of 15 files for each technical spec.
- The open questions in the design specs: to refuse past dates, to store registrations in upper case, and to record step times.
- Which review findings to fix and which to record as limitations.

How the output was checked:

- **Tests first.** For each feature the tests were written from the acceptance criteria and run before the code existed, to see them fail for the expected reason.
- **One script for every check.** `./start.sh` was run before each pull request.
- **Running the application.** The API was called on a running server, and the UI was used in a browser, for every feature.
- **Deliberate bugs.** The code was broken on purpose, one change at a time, to see whether a test failed. The first round caught 19 of 28. The gaps were closed, and a second round on other parts of the code caught 6 of 10. Those gaps were closed too. These numbers describe the bugs that were tried, not the strength of the tests as a whole.
- **Two reviews.** The first looked at test coverage. The second started from scratch and is on the pull requests as review comments, with a reply for each finding saying what was done and whether it was run or only read. It found three serious problems. Two were tests that could not fail for the bug they were meant to catch, and the first review had missed both.
- **Premises checked in the framework's source.** Each technical spec lists the assumptions about Laravel that were checked before the code was written.

What this did not catch on its own: both of those test gaps were found only when I asked for a second review from scratch. After the first review the assistant reported that 27 of 28 deliberate bugs were caught, which overstated the tests, because it had chosen the 28 itself.
