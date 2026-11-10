# TS-004 — Create and select inspections in the UI

| | |
|---|---|
| Design spec | [DS-004](ds-004.md), acceptance criteria UI-401 to UI-410 |
| Depends on | [TS-001](../SPEC-0001/ts-001-create-inspection.md), [TS-002 UI](../SPEC-0002/ts-002-view-inspections-ui.md), [TS-003 UI](../SPEC-0003/ts-003-inspection-workflow-ui.md) |
| Files changed | 6 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). No deviations.

## 1. Approach

The API is not changed: `POST /api/inspections` exists and is tested. The page gains one function in `api.ts` to call it, and one component for the form. The right panel shows either the detail or the form, chosen by one piece of state in `app.tsx`. The list makes selection obvious: the whole row is clickable, each row has a "View" button, and the newest inspection is selected after the first load.

## 2. Tech stack & dependencies

Existing stack only. No new dependency.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `resources/js/types.ts` | Modified | Adds `NewInspection`, the four fields the form sends |
| 2 | `resources/js/api.ts` | Modified | Adds `createInspection()`. `ApiError` gains `fieldErrors` |
| 3 | `resources/js/components/CreateInspectionForm.tsx` | New | The four fields, their error messages, and the two buttons |
| 4 | `resources/js/components/InspectionList.tsx` | Modified | The whole row selects. Adds the "View" button |
| 5 | `resources/js/app.tsx` | Modified | Holds whether the form is open. Selects the newest inspection after the first load. Adds a created inspection to the list |
| 6 | `README.md` | Modified | How to create an inspection from the page. Removes the UI create form from what is missing. Says how sending details to a customer would be approached |

Also changed, and not counted because they are specs: `specs/SPEC-0001/ds-001.md` and `specs/SPEC-0002/ts-002-view-inspections-ui.md`, which both said the create form was not built, now point here.

## 4. Data model

No database change.

`NewInspection`, the body sent to `POST /api/inspections`:

| Field name | Type | Required (Y/N) | Comments |
|---|---|---|---|
| `customer_name` | `string` | Y | |
| `vehicle_registration` | `string` | Y | |
| `requested_date` | `string` | Y | `YYYY-MM-DD`, as an `<input type="date">` provides it |
| `notes` | `string` | Y | An empty string when the user leaves it empty. The server stores that as null (TS-001) |

"Required" here describes the TypeScript type, not a rule. Whether a value is acceptable is decided by the server.

## 5. State, persistence & execution mode

The server is the source of truth. State added to `app.tsx`:

| State | Type | Meaning |
|---|---|---|
| `creating` | `boolean` | True while the right panel shows the form |

State local to `CreateInspectionForm`:

| State | Type | Meaning |
|---|---|---|
| `values` | `NewInspection` | What the user has typed |
| `submitting` | `boolean` | True while the request is in flight. The buttons are disabled |
| `fieldErrors` | `Record<string, string[]>` | The server's messages per field, from the last 422 |
| `formError` | `string \| null` | The message from a failure that is not about a field |

The typed values live in the form component, so they are kept across a refused submission (UI-404, UI-405) and are discarded when the form closes.

## 6. Interfaces

| Interface | Type | Contract |
|---|---|---|
| `createInspection(input: NewInspection): Promise<Inspection>` | TypeScript function in `api.ts` | Calls `POST /api/inspections`. Returns `data`. Throws `ApiError` on failure |
| `ApiError.fieldErrors` | Property | `Record<string, string[]>`. The `errors` object of a 422 response, and an empty object for any other failure |
| `<CreateInspectionForm onCreated onCancel />` | React component | Calls `onCreated(inspection)` after a 201, and `onCancel()` when the user cancels |
| `<InspectionList inspections selectedId onSelect />` | React component | Unchanged props. `onSelect(id)` is now called for a click anywhere on a row, and for the "View" button |

`ApiError.message` keeps its present meaning, the first field error or the server's message, so the existing callers in TS-003 UI are not affected.

## 7. Flow, navigation & data movement

**First load (UI-408).** When the list has loaded and no inspection is selected, `app.tsx` selects the first one. The list is newest first, so that is the newest. With an empty list nothing is selected, and the right panel says "No inspection selected. Create one with New inspection."

**Selecting (UI-409, UI-410).** A click on a row, or on its "View" button, calls `onSelect(id)`. Selecting also closes the form if it is open.

**Creating.**

1. The user chooses "New inspection". `creating` becomes true and the right panel shows the form (UI-401).
2. The user submits. `submitting` becomes true and both buttons are disabled (UI-407).
3. The form calls `createInspection(values)`.
4. On success: the form calls `onCreated(inspection)`. `app.tsx` puts the inspection at the start of `inspections`, selects it, and sets `creating` to false. The right panel shows its detail, with the control to assign an inspector (UI-402, UI-403).
5. On a 422: `fieldErrors` is set from `ApiError.fieldErrors`, and each message is shown under its field (UI-404).
6. On any other failure: `formError` is set to `ApiError.message` (UI-405).
7. `submitting` becomes false. After step 5 or 6 the typed values are still in the fields.

**Cancelling (UI-406).** `creating` becomes false. `selectedId` was not changed by opening the form, so the right panel shows the same inspection as before.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Validation | None in the page. No field is marked `required`, and the date input has no `min`. The server decides, and its messages are shown. This follows `docs/architecture.md`: the React app does not repeat the rules |
| Invariants | All HTTP calls go through `api.ts`. A created inspection is added to the list only from the server's 201 response, never from what the user typed, so the list shows the stored form: upper-case registration, null notes |
| Error handling | 422: messages per field. Anything else, including a network failure: one message in the form |
| User/system visibility | A field's messages are shown directly under it and tied to it with `aria-describedby`. The form-level message has `role="alert"` |

A message for a field the form does not have cannot occur today, because the form sends exactly the four fields the server validates. If it did, it would be shown as the form-level message, so that no error is lost.

## 9. Build, tooling & quality gates

No change to `start.sh`. Its type check, ESLint, Prettier check and build cover the changed files.

| Area | Notes |
|---|---|
| Migrations | None |
| Config | None |
| Observability | None |
| Deployment | Out of scope |
| Performance | Creating does not reload the list. One request, and the response is added to the list in memory |

## 10. Testing strategy

No automated UI tests, as in the earlier UI specs (see OQ-404 in DS-004). The server's rules for the four fields are covered by the tests of TS-001. UI-401 to UI-410 are checked by hand in a browser, and the results are recorded in this table after the build.

| Check | How | Result |
|---|---|---|
| UI-408 | Load the page with seeded data. Then load it against an empty table | To be recorded |
| UI-409, UI-410 | Click a row outside its text. Then select another row with Tab and Enter only | To be recorded |
| UI-401, UI-402, UI-403 | Create an inspection with a lower-case registration and no notes. Compare the new row and its detail with `GET /api/inspections` | To be recorded |
| UI-404 | Submit the empty form. Then submit a past date with the other fields filled in | To be recorded |
| UI-405 | Stop the server, then submit valid input | To be recorded |
| UI-406 | Select an inspection, open the form, type in it, and cancel | To be recorded |
| UI-407 | Delay the create endpoint for a few seconds, as was done for UI-202, and try to submit twice | To be recorded |
| Regression | Repeat the TS-003 UI checks UI-301 to UI-305 on an inspection created from the page | To be recorded |

Why this is acceptable: the page holds no rules. A wrong value is caught by the server, whose tests exist. What can break here is wiring, and each wire is exercised once by the checks above.

Not tested: a failed request at the moment the user cancels.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | The form is its own component and holds what the user types | The values held in `app.tsx` | Only the form needs them. They are discarded when it closes, without any reset code |
| D-2 | `ApiError` gains `fieldErrors` | A second error class for validation failures | One class, one `catch`. The existing callers read `message` and are not affected |
| D-3 | The new inspection is added to the list from the 201 response | Reload the list after creating | One request in place of two, and the row cannot differ from what was stored |
| D-4 | Whole-row click and a "View" button | A link on the registration only, as before | The reviewer did not find the link. The button is also what a keyboard user operates, since a table row cannot take focus by default |
| D-5 | No client-side validation | `required` attributes and a `min` on the date | See section 8. One set of rules, on the server |

### Verified against the code

Checked on `dev` before this spec was written.

| Premise | Where it was checked |
|---|---|
| `ApiError` keeps only the first message of a 422, so the form cannot show errors per field today | `resources/js/api.ts`, `messageFrom()` |
| A 422 body has `errors` keyed by field name | TS-001 section 6, and the AC-106 test |
| The list is newest first, so its first item is the newest inspection | `InspectionController::index`, and the AC-201 test |
| Only the registration text in a row can be clicked today | `resources/js/components/InspectionList.tsx` |
| `<input type="date">` gives `YYYY-MM-DD`, the format the server requires | HTML specification. To be confirmed in the browser as part of UI-402 |

### Open questions

None. OQ-401 to OQ-404 in DS-004 were decided by the author, and this spec follows the outcomes.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Automated UI tests | OQ-404. Listed first under "With more time" in the README |
| DF-2 | Sending the inspection details to the customer | A notification, which the brief puts out of scope |
| DF-3 | Editing or deleting an inspection | Not in any story |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-1 | The UI is checked by hand only | As in the earlier UI specs. The server's rules are tested, and the page holds none |
| R-2 | Two users who create at the same time do not see each other's inspection until they reload | Accepted. The list has always been loaded once per page view (TS-002 UI DF-3) |
| R-3 | A browser that does not support `<input type="date">` shows a text field, and the user may type another format | The server refuses it with a message under the field (UI-404) |
