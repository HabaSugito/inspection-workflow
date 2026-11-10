# TS-003 — Progress an inspection: UI

| | |
|---|---|
| Design spec | [DS-003](ds-003.md), acceptance criteria UI-301 to UI-306 |
| Depends on | [TS-003 workflow](ts-003-inspection-workflow.md), [TS-002 UI](../SPEC-0002/ts-002-view-inspections-ui.md) |
| Files changed | 5 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). No deviations.

## 1. Approach

The detail panel gains one control, chosen by the `next_action` the API returns, so the UI contains no workflow rules. Triggering it calls the matching endpoint and replaces that inspection in the list with the one returned. If the call is refused or fails, the message is shown and the list is reloaded so the user sees the inspection's actual status.

## 2. Tech stack & dependencies

Existing stack only. No new dependency.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `resources/js/types.ts` | Modified | Adds `NextAction`, and `next_action` and the three times to `Inspection` |
| 2 | `resources/js/api.ts` | Modified | Adds `assignInspector`, `startInspection`, `completeInspection` |
| 3 | `resources/js/components/NextActionControl.tsx` | New | The control for one `next_action` |
| 4 | `resources/js/components/InspectionDetail.tsx` | Modified | Shows the step times, the control and any action error |
| 5 | `resources/js/app.tsx` | Modified | Runs an action, updates the list, handles failure |

## 4. Data model

No database change. Added to the `Inspection` type.

| Field name | Type | Required (Y/N) | Comments |
|---|---|---|---|
| `next_action` | `NextAction \| null` | Y | `'assign' \| 'start' \| 'complete'`. Null when completed |
| `assigned_at` | `string \| null` | Y | ISO 8601 |
| `started_at` | `string \| null` | Y | ISO 8601 |
| `completed_at` | `string \| null` | Y | ISO 8601 |

## 5. State, persistence & execution mode

The server is the source of truth. State added to `app.tsx`:

| State | Type | Meaning |
|---|---|---|
| `pendingAction` | `boolean` | True while an action request is in flight. The control is disabled |
| `actionError` | `string \| null` | Message from the last failed action. Cleared when another row is selected or an action succeeds |

The inspector name being typed is local state of `NextActionControl`.

## 6. Interfaces

| Interface | Type | Contract |
|---|---|---|
| `assignInspector(id, inspectorName): Promise<Inspection>` | TypeScript function in `api.ts` | Calls `POST /api/inspections/{id}/assign` |
| `startInspection(id): Promise<Inspection>` | TypeScript function in `api.ts` | Calls `POST /api/inspections/{id}/start` |
| `completeInspection(id): Promise<Inspection>` | TypeScript function in `api.ts` | Calls `POST /api/inspections/{id}/complete` |
| `<NextActionControl action disabled onAssign onStart onComplete />` | React component | Renders the control for `action`, or nothing when it is null |
| `<InspectionDetail inspection pendingAction actionError onAssign onStart onComplete />` | React component | Adds the step times, the error message and the control to the detail panel |

Each function returns `data` from the response and throws `ApiError` with the response's `message` on failure. For a 422, the first field error is used as the message.

## 7. Flow, navigation & data movement

1. The user selects an inspection. The detail panel shows its fields, the times of the steps taken (UI-306), and the control for `next_action`:

   | `next_action` | Control |
   |---|---|
   | `assign` | Inspector name input and an "Assign inspector" button (UI-301) |
   | `start` | "Start inspection" button (UI-302) |
   | `complete` | "Complete inspection" button (UI-303) |
   | `null` | Nothing (UI-303) |

2. The user triggers the control. `pendingAction` becomes true and the control is disabled.
3. `app.tsx` calls the matching function in `api.ts`.
4. On success, the returned inspection replaces the one with the same ID in `inspections`. The list row and the detail panel both show the new status (UI-304), and the control changes to the next action.
5. On failure, `actionError` is set to the message, and the list is reloaded so the current status is shown (UI-305).
6. `pendingAction` becomes false.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Validation | The "Assign inspector" button is disabled while the name is empty or only spaces. The server remains the authority and still validates |
| Invariants | The control is chosen only from `next_action`. No component compares `status` to decide what is allowed. All HTTP calls go through `api.ts` |
| Error handling | 409, 422, 404, network failure and any other error are handled the same way: show the message, reload the list |
| User/system visibility | The message is shown in the detail panel, above the control |

## 9. Build, tooling & quality gates

No change to `start.sh`. Its type check, ESLint, Prettier check and build cover the changed files.

| Area | Notes |
|---|---|
| Migrations | None |
| Config | None |
| Observability | None |
| Deployment | Out of scope |
| Performance | Not a concern |

## 10. Testing strategy

No automated UI tests. UI-301 to UI-306 are checked by hand. The steps and the results are below, and the README repeats the steps.

| Check | How | Result |
|---|---|---|
| UI-301 to UI-304, UI-306 | With seeded data, take one pending inspection through assign, start and complete. After each step, check the list row, the detail panel, the step times and the control | Passed. The input and "Assign inspector" button were shown, with the button disabled while the name was empty. After each step the row and the panel showed the new status, the step's time appeared, and the control changed to the next action |
| UI-303 | Select a completed inspection and confirm that no control is shown | Passed. The detail panel held no button or input |
| UI-305 | Select a pending inspection. Assign it from outside the page, as a second tab would, then try to assign it in the page | Passed. The page showed "This inspection is assigned. The only action allowed is: start.", the row and the panel changed to `assigned`, and the inspector set by the first request was kept. The outside request was sent to the API directly instead of from a second tab |

Why this is acceptable: the rules are on the server and are covered by the tests in TS-003 workflow. The UI only renders `next_action`, and `tsc` checks that every value of `NextAction` is handled.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | The control is chosen from `next_action` | Deriving it from `status` in TypeScript | The workflow rules stay on the server |
| D-2 | After a failed action the list is reloaded | Only showing the message | A 409 means the copy in the browser is out of date. Reloading shows the actual status |
| D-3 | All action failures are handled the same way | Separate handling per status code | One path is simpler, and the server's message already says what went wrong |
| D-4 | The control is its own component | Inline in `InspectionDetail` | Keeps the switch over `NextAction` in one small file that `tsc` can check for exhaustiveness |

### Open questions

None.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Automated UI tests | See section 10 |
| DF-2 | Showing times in the user's preferred format and timezone | Not in any story. The browser's locale format is used |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-1 | The UI is only checked by hand | The UI holds no rules. `tsc`, ESLint and the build are run by `start.sh` |
| R-2 | The list in one browser tab goes stale when another user acts | An action on a stale row is refused by the server and the list is then reloaded (D-2) |
