---
description: Scaffold a Gateway REST endpoint from the OpenAPI contract (Laraship module)
argument-hint: <method> <path>  e.g. POST /v1/payment-intents
---

Read `Corals/modules/Gateway/CLAUDE.md`, `contracts/openapi.yaml`, and the relevant docs. Use
`search-docs` before Laravel/package changes (root rule).

Endpoint: `$1 $2`

Do this:

1. Add/confirm the operation in `contracts/openapi.yaml` (request + response schemas + examples). The
   contract is the source of truth — edit it FIRST, then implement to match.
2. Route + Controller the Laraship way:
   - Controller extends `Corals\Foundation\Http\Controllers\APIBaseController` (or `BaseController` for
     admin views); add a FormRequest for validation; register the route in the module's `routes/`.
   - Auth via **Sanctum** for POS/issuer APIs; wrap privileged ops with a `BasePolicy` check.
   - API output via a Fractal **Transformer** (module convention), never a raw model dump; IDs as Hashids.
3. **Idempotency:** mutating ops require an `Idempotency-Key` header; short-circuit replays with the
   stored response snapshot (Core-owned). Controllers orchestrate; they NEVER write the ledger directly —
   delegate to `Core\*` services inside a DB transaction. Money in/out is integer centavos.
4. **PHPUnit** feature test (`make:test --phpunit`): happy path, idempotent replay, validation failure,
   and — for POS confirm — duplicate `network_txn_id` rejection. Use factories; real DB (Postgres).

Summarize the contract change + files created.
