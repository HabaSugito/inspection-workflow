# TS-004 — Automated tests for the UI

| | |
|---|---|
| Design spec | [DS-004](ds-004.md), decision OQ-404. Covers UI criteria from DS-002, DS-003 and DS-004 |
| Depends on | [TS-004 create and select](ts-004-create-and-select-ui.md) |
| Files changed | 13 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). One addition: frontend tests live beside the code they test, under `resources/js`. The architecture document is updated to say so.

## 1. Approach

The UI gets automated tests that render the components in a simulated browser and operate them as a user would: by role and label, with clicks and key presses. The server is replaced at one seam, the functions of `api.ts`, which the architecture already makes the only way to reach it. `api.ts` itself is tested against a replaced `fetch`. `./start.sh` runs the new tests with every other check.

The page component is moved out of the entry file so that it can be rendered by a test. The entry file keeps only the mounting.

## 2. Tech stack & dependencies

All four are development dependencies. None is shipped to the browser.

| Package | Why needed | Alternatives considered | Why not avoidable |
|---|---|---|---|
| `vitest` | Runs the tests. It reads the project's Vite setup, so TypeScript and JSX need no second build configuration | Jest | Jest needs its own transform setup for TypeScript and JSX, which would duplicate what Vite already does |
| `jsdom` | Gives the tests a DOM to render into | A real browser driven by Playwright | A browser is slower, needs a running server and a browser download, and is more than component tests need |
| `@testing-library/react` | Renders a component and finds elements by role and label, as assistive technology does | Asserting on React's own output | Tests written against roles and labels do not break when markup changes, and they fail when a label is missing |
| `@testing-library/user-event` | Types and clicks as a user does, with the full sequence of browser events | `fireEvent` from the package above | `fireEvent` sends one synthetic event. It would not have shown the double-submit defect of TS-004 DV-1, which depends on real event order |

Not added: `@testing-library/jest-dom`. Its matchers are a convenience, and plain assertions on the DOM are enough here.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `package.json` | Modified | The four packages, and a `test` script. `package-lock.json` is generated and not counted |
| 2 | `vitest.config.ts` | New | The test environment and where the tests are. Uses the React plugin only, not the Laravel plugin |
| 3 | `resources/js/test-setup.ts` | New | Unmounts rendered components after each test |
| 4 | `resources/js/components/InspectionsPage.tsx` | New | The page component, moved unchanged from `app.tsx` |
| 5 | `resources/js/app.tsx` | Modified | Mounts `InspectionsPage`. Nothing else |
| 6 | `resources/js/api.test.ts` | New | Section 10 |
| 7 | `resources/js/components/InspectionList.test.tsx` | New | Section 10 |
| 8 | `resources/js/components/NextActionControl.test.tsx` | New | Section 10 |
| 9 | `resources/js/components/CreateInspectionForm.test.tsx` | New | Section 10 |
| 10 | `resources/js/components/InspectionsPage.test.tsx` | New | Section 10 |
| 11 | `start.sh` | Modified | Runs `npm test` |
| 12 | `docs/architecture.md` | Modified | Where frontend tests live, and that `start.sh` runs them |
| 13 | `README.md` | Modified | The test command and count. Removes "no automated UI tests" from the limitations |

## 4. Data model

No change.

## 5. State, persistence & execution mode

No change to the application. Each test renders into a fresh DOM, and every replaced function is reset between tests, so no test depends on another.

## 6. Interfaces

| Interface | Type | Contract |
|---|---|---|
| `npm test` | Command | Runs every `*.test.ts` and `*.test.tsx` under `resources/js` once and exits with an error if any fails |
| `<InspectionsPage />` | React component | The whole page. No props. This is what `app.tsx` mounts |

## 7. Flow, navigation & data movement

No change to the application's flow. The page component moves file, and its code does not change.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Invariants | Component tests replace the functions of `api.ts` and nothing below them. `api.test.ts` replaces `fetch` and nothing above it. Together they cover the path from a click to a request without a server |
| Invariants | Tests find elements by role, label or visible text. No test uses a CSS class or a test ID |

## 9. Build, tooling & quality gates

`./start.sh` runs `npm test` after the type check. The test files are under `resources/js`, so the existing type check, ESLint and Prettier check cover them too.

## 10. Testing strategy

Each test name starts with the ID of the criterion it covers.

| File | Covers |
|---|---|
| `api.test.ts` | A successful list. An error response becomes an `ApiError` with its status and the server's message. A 422 carries its errors per field. A network failure has status 0. A successful response that is not JSON is an `ApiError`. `createInspection` and `assignInspector` send `POST` with a JSON body to the right path |
| `InspectionList.test.tsx` | UI-201: each row shows status, registration and customer. UI-409: a click on a cell selects the row. UI-410: the "View" button is reached with Tab and used with Enter, and only the selected row's button is pressed |
| `NextActionControl.test.tsx` | UI-301: the input and "Assign inspector", disabled for an empty name or spaces, and the name is sent trimmed. UI-302 and UI-303: the start and complete buttons, and nothing when there is no next action. The control is disabled while an action is pending |
| `CreateInspectionForm.test.tsx` | UI-401: four fields and two buttons. UI-402: the typed values are sent and the server's inspection is handed on. UI-404: messages under their fields, tied with `aria-describedby`, values kept. UI-405: one message for another failure, values kept. UI-406: cancel sends nothing. UI-407: two submits in a row send one request, and the form is disabled meanwhile. OQ-403: an empty form is sent to the server, not blocked by the page |
| `InspectionsPage.test.tsx` | UI-202, UI-203, UI-204: loading, empty and error. UI-408: the newest inspection is selected after loading. UI-402: a created inspection is first in the list and selected, and the form closes. UI-406: cancel shows the inspection selected before. UI-304: after a step the row and the detail show the new status. UI-305: a refused step shows the message and reloads the list. The message is not shown after another inspection is selected |

These tests are written after the code they cover, so they cannot be seen to fail first. In place of that, deliberate bugs are introduced one at a time after the tests pass, and the result is recorded here.

| Deliberate bug | Result |
|---|---|
| To be recorded after the build | |

Not tested: layout and colour, the browser's own date picker, and the real server. The PHP tests cover the server, and section 10 of the two UI specs records the checks made in a real browser.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | The server is replaced at `api.ts` | Replace `fetch` in every test, or run a mock server | `api.ts` is the one place the architecture allows HTTP. Replacing it keeps component tests about components |
| D-2 | A separate technical spec and pull request | Add the tests to TS-004 create and select | DS-004 said the dependencies should be reviewed on their own. It also keeps that spec within 15 files |
| D-3 | The page component moves to its own file | Export it from `app.tsx` | Importing `app.tsx` mounts the application as a side effect, which a test must not trigger |
| D-4 | Tests sit beside the code, as `*.test.tsx` | A separate folder under `tests/` | `tests/` holds the PHP tests and is configured for PHPUnit. Beside the code, a missing test is easy to see |

### Verified against the code

| Premise | Where it was checked |
|---|---|
| The installed Vitest works with the project's Vite 8 | `npm ls vite` after installing: one copy of Vite, 8.3.7 |
| `app.tsx` mounts the page when it is imported | `resources/js/app.tsx`: `createRoot(...)` runs at the top level |
| Components reach the server only through `api.ts` | `grep` for `fetch` under `resources/js`: `api.ts` only |
| The install reports no new audit warning | `npm audit` before and after: the same warnings, all from ESLint's dependencies |

### Open questions

None.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Tests in a real browser against the real server | A different tool and a running server. The checks by hand cover this today |
| DF-2 | The audit warnings from ESLint's dependencies | They are in development tooling only, and the fix offered is a breaking upgrade |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-1 | A test passes against the replaced `api.ts` while the real API has changed shape | The PHP test for AC-203 compares the whole response body, so a change of shape fails there. See TS-002 UI R-3 |
| R-2 | jsdom is not a browser. Focus, layout and the date picker behave differently | The checks by hand in the two UI specs stay. These tests add to them and do not replace them |
