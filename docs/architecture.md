# Architecture

One Laravel project holds the JSON API and the React app.

```
Browser ── React app (resources/js) ── fetch ──▶ /api/* (Laravel) ──▶ SQLite
```

## Layers

A request passes through these layers, in this order.

| Layer | Location | Does | Does not |
|---|---|---|---|
| Route | `routes/api.php` | Maps a URL to a controller method | Contain logic |
| Controller | `app/Http/Controllers` | Calls a service or a query and returns a resource | Validate input or decide business rules |
| Form request | `app/Http/Requests` | Validates input | Read or write the database |
| Service | `app/Services` | Applies business rules and writes changes | Know about HTTP |
| Enum | `app/Enums` | Defines fixed sets of values and the rules between them | Touch the database |
| Model | `app/Models` | Maps a table to an object | Hold workflow rules |
| Resource | `app/Http/Resources` | Defines the JSON shape | Change data |

## Rules

- Business rules live on the server. The React app shows what the API returns and does not repeat the rules.
- Only a service may change an inspection's status.
- Every response under `/api`, including errors, is JSON.
- The React app reaches the server only through `resources/js/api.ts`.

## Frontend

| Location | Holds |
|---|---|
| `resources/js/app.tsx` | Entry point and page state |
| `resources/js/api.ts` | All HTTP calls |
| `resources/js/types.ts` | Types for API data |
| `resources/js/components` | Presentational components. They receive data and callbacks as props |

## Tests

| Location | Level | Use for |
|---|---|---|
| `tests/Unit` | Unit | Code with no database or HTTP, such as enums |
| `tests/Feature` | Feature | HTTP endpoints, and services that need the database. Runs against an in-memory SQLite database |

`./start.sh` runs every test and check. A test placed anywhere else is not run.

## Baseline

The baseline is the starting point that every technical spec builds on. It is not covered by a spec and its files do not count towards a spec's file limit.

- The Laravel 13 skeleton, as created by `composer create-project laravel/laravel`, with its example tests removed. The skeleton's own `AGENTS.md`, `CLAUDE.md` and `README.md` are not used: this repository has its own.
- `start.sh`, which installs dependencies, prepares the database and runs every check.
- `docs/architecture.md`, `AGENTS.md` and the specs.

## Specs

Specs live under `specs/`.

| File | Holds |
|---|---|
| `ds-NNN.md` | A design spec: what is built. At most about four user stories |
| `ts-NNN-<name>.md` | A technical spec for design spec `NNN`: how it is built. It changes at most 15 files. A design spec that needs more is split into several technical specs with different names |

A spec is written and reviewed before code, and updated afterwards to match what was built.
