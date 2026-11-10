# TS-002 — View inspections: UI

| | |
|---|---|
| Design spec | [DS-002](ds-002.md), stories US-203 and US-204 |
| Depends on | [TS-002 API](ts-002-view-inspections-api.md) |
| Files changed | 6 |

**Aligns to architecture:** [docs/architecture.md](../../docs/architecture.md). No deviations.

## 1. Approach

The baseline already serves one page at `/` that mounts an empty React app. This spec makes the app load the list once from `GET /api/inspections`. The page state lives in the top component; the list and detail components only receive props. This spec is read-only: the controls that change an inspection come in TS-003.

## 2. Tech stack & dependencies

Existing stack only, as set up by the baseline: React 19, TypeScript, Vite 8 and Tailwind CSS 4. No new dependency. Styling uses Tailwind classes in the components.

Not added: a router, a state library, a data-fetching library, a UI kit, an HTTP client. The browser's `fetch` is used for HTTP.

## 3. Module / component breakdown

| # | File | Change | Responsibility |
|---|---|---|---|
| 1 | `resources/js/app.tsx` | Modified | Holds the list, the selected ID and the load state |
| 2 | `resources/js/api.ts` | New | `listInspections()`. Throws `ApiError` on a failed response |
| 3 | `resources/js/types.ts` | New | `Inspection`, `InspectionStatus` |
| 4 | `resources/js/components/InspectionList.tsx` | New | The rows. Reports which row was selected |
| 5 | `resources/js/components/InspectionDetail.tsx` | New | The fields of one inspection |
| 6 | `resources/js/components/StatusBadge.tsx` | New | Status label, used by the list and the detail |

## 4. Data model

No database change. The TypeScript types mirror the resource in TS-001 section 6.

| Field name | Type | Required (Y/N) | Comments |
|---|---|---|---|
| `id` | `number` | Y | |
| `customer_name` | `string` | Y | |
| `vehicle_registration` | `string` | Y | |
| `requested_date` | `string` | Y | `YYYY-MM-DD` |
| `notes` | `string \| null` | Y | Null when there are no notes |
| `status` | `InspectionStatus` | Y | `'pending' \| 'assigned' \| 'in_progress' \| 'completed'` |
| `inspector_name` | `string \| null` | Y | Null until assigned |
| `created_at`, `updated_at` | `string` | Y | ISO 8601 |

A field that is nullable in the database is nullable in the type.

## 5. State, persistence & execution mode

The server is the source of truth. The browser holds a copy for the life of the page and stores nothing.

State in `app.tsx`:

| State | Type | Meaning |
|---|---|---|
| `inspections` | `Inspection[]` | The list as last loaded |
| `selectedId` | `number \| null` | The row the user selected |
| `load` | `'loading' \| 'ready' \| 'error'` | State of the initial request |

The selected inspection is found in `inspections` by `selectedId`. It is not stored separately, so the list and the detail cannot disagree.

## 6. Interfaces

| Interface | Type | Contract |
|---|---|---|
| `GET /` | Web page | Returns the page that mounts the React app |
| `listInspections(): Promise<Inspection[]>` | TypeScript function in `api.ts` | Calls `GET /api/inspections` and returns `data`. Throws `ApiError` if the response is not 2xx or the request fails |
| `ApiError` | TypeScript class in `api.ts` | Carries `status` (HTTP status, or 0 for a network failure) and `message` |
| `<InspectionList inspections selectedId onSelect />` | React component | Renders the rows and calls `onSelect(id)` |
| `<InspectionDetail inspection />` | React component | Renders the fields of one inspection |
| `<StatusBadge status />` | React component | Renders the status as a label |

## 7. Flow, navigation & data movement

One page, no navigation.

1. The browser requests `/`. Laravel returns the page and the browser loads the bundle.
2. `app.tsx` mounts with `load = 'loading'` and shows "Loading inspections…" (UI-202).
3. It calls `listInspections()`.
4. On success: `load = 'ready'`. With rows, the list is shown (UI-201). With none, "No inspections yet." is shown (UI-203).
5. On failure: `load = 'error'` and the error message is shown (UI-204).
6. The user selects a row. `selectedId` is set, the row is marked, and the detail panel shows that inspection (UI-205). Before any selection the panel shows "Select an inspection to see its details."

The detail panel uses the data already in the list. `GET /api/inspections/{id}` exists for API clients and is not called by the UI.

## 8. Validation, invariants & error handling

| Category | Rules |
|---|---|
| Validation | None. The page takes no input in this spec |
| Invariants | All HTTP calls go through `api.ts`. Components do not call `fetch` |
| Error handling | Any failed load is shown as one message. There is no automatic retry; the user reloads the page |
| User/system visibility | The message is shown in place of the list |

Null values are shown as "—", so `notes` and `inspector_name` never render as the text "null".

## 9. Build, tooling & quality gates

`./start.sh` already runs the type check (`tsc --noEmit`), ESLint, the Prettier check and the frontend build. A failure in any of them stops the script.

| Area | Notes |
|---|---|
| Migrations | None |
| Config | None |
| Observability | None |
| Deployment | Out of scope |
| Performance | Not a concern at sample-data size |

## 10. Testing strategy

No automated UI tests. UI-201 to UI-205 are checked by hand. The steps and the results are below, and the README repeats the steps.

| Check | How | Result |
|---|---|---|
| UI-201, UI-205 | Run with seeded data. Compare the rows and the detail panel with the API response | Passed. Five rows matched the API response. Selecting a row marked it and showed its fields. A null inspector showed as "—" |
| UI-202 | Reload with the network throttled in the browser's developer tools | Not checked. The browser used for the checks could not throttle the network. The message is rendered by the same condition as the others |
| UI-203 | Run against an empty table (`php artisan migrate:fresh` without `--seed`) | Passed. "No inspections yet." was shown |
| UI-204 | Make `GET /api/inspections` fail, then reload the page | Passed. With the `inspections` table dropped the API returned 500, and the page showed "Could not load inspections." followed by the API's message |

Why this is acceptable: the brief asks for the backend and its tests to take priority, and this UI holds no rules. `tsc` checks that the UI uses the API types correctly, ESLint checks the rules of hooks, and `npm run build` checks that it compiles.

## 11. Decisions, open questions, deferred items & risks

Owner of every item: the author.

### Decisions

| ID | Decision | Alternative considered | Reason |
|---|---|---|---|
| D-1 | API and UI in one Laravel project | A separate frontend project | One install and one command to run, with no CORS setup |
| D-2 | State held in `app.tsx` with `useState` and `useEffect` | A state or data-fetching library | One list and one selection do not justify a dependency |
| D-3 | The detail panel reads from the loaded list | Calling `GET /api/inspections/{id}` on selection | Saves a request and keeps the list and the detail consistent |
| D-4 | Styling with the Tailwind CSS that the skeleton ships | Hand-written CSS, or a UI kit | It is already installed and configured, so nothing is added |
| D-5 | TypeScript, which the brief makes optional | Plain JavaScript | The API contract is explicit in the UI code and checked by `start.sh` |

### Verified against the baseline

| Premise | Where it was checked |
|---|---|
| `/` serves a page that mounts a React app from `resources/js/app.tsx` | Opened in a browser: the heading renders and the console has no errors |
| No HTTP client is installed | `package.json` |

### Open questions

None.

### Deferred

| ID | Item | Why deferred |
|---|---|---|
| DF-1 | Automated UI tests | See section 10 |
| DF-2 | A create form | Not in any story |
| DF-3 | Refreshing the list without reloading the page | Not in any story |

### Risks

| ID | Risk | Mitigation |
|---|---|---|
| R-2 | With `APP_DEBUG=true`, a server error message shown by the page includes internal detail such as SQL | This is Laravel's debug behaviour and applies to local runs only. With debug off the API answers "Server Error" |
| R-1 | The UI is only checked by hand, so a regression would not be caught by `start.sh` | The UI holds no rules. `tsc`, ESLint and the build are run by `start.sh` |
