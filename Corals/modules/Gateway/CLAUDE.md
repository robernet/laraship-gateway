# CLAUDE.md — Gateway module (`Corals\Modules\Gateway`)

> **This file layers UNDER the root `CLAUDE.md`.** The root's Behavioral Guidelines, PHP conventions,
> Laravel/Boost rules, and testing rules all apply here unchanged. This file adds only what is specific
> to the Gateway module. On conflict, the stricter rule wins; anything about *money or state* below is
> non-negotiable and overrides convenience.

## What this module is

A production-grade **cash-collection payment gateway** for Mexico, built as a single Laraship dynamic
module. An **Issuer** generates cash payment instruments (human reference + barcode + signed QR) tied to
an `invoice_id`; a customer pays cash at a physical **POS** in a retail network; the gateway confirms in
real time, credits the issuer, and reconciles/settles. v1 is **clean-room + generic + mock networks**;
real Mexican networks and ClubPago layouts are later fidelity layers mapped onto the same core.

Full design lives in `docs/` — read `docs/architecture.md`, `docs/data-model.md`,
`docs/state-machines.md`, `docs/reference-qr-spec.md`, `docs/adapter-contract.md`,
`docs/security-antifraud.md`, `docs/settlement-reconciliation.md`. `contracts/` is the source of truth
for every boundary; edit contracts before code.

## The financial model is PREPAID (read twice)

POS collection points **pre-fund a wallet** with the gateway (SPEI/CLABE top-up). Every confirmed
collection **draws that wallet down in real time**. The physical cash stays at the store and is what it
uses to top the wallet back up. Consequences that are load-bearing everywhere:

- The ledger is **wallet-centric**: every confirmed collection is ONE atomic double-entry posting —
  debit `pos_wallet` / credit `issuer_payable` (+ commission + fee legs). No exceptions.
- `validate` must check `pos_wallet.available >= amount_attempt` and reserve. Insufficient → decline.
- **Overdraft is impossible by construction** (see the conditional-update invariant below).
- **Finality is on `confirm`** (prepaid removes credit risk). Corrections are time-boxed **voids**,
  never cash clawbacks.
- Prepaid balances = **stored value** → IFPE / Ley Fintech + PLD/AML. Compliance is a design input.

## Stack (module-level, locked)

- **PostgreSQL** — deliberate override of Laraship's MySQL default, chosen for CHECK/EXCLUSION
  constraints guarding ledger invariants. The app's default connection is Postgres.
- **Laravel 13 + PHP 8.3** (matches root). **Sanctum** for POS/issuer API auth.
- Money = **integer centavos (`BIGINT`)**. Never float. Public IDs = **Hashids** (`hashids()->encode/decode`).
- **Redis** (Laraship already ships `predis`) for the replay/nonce cache + queue.

## Laraship mapping (how this module is organized)

Standard Laraship module folders (mirror a sibling module — check `Corals/modules/*` for exact shape):
```
Corals/modules/Gateway/
  module.json                       # manifest (name, version, providers, dependencies)
  routes/                           # issuer + pos route files
  database/migrations/
  resources/views/                  # admin + receipts (themed, Vue2/BS4)
  Http/Controllers/                 # extend Corals\Foundation\Http\Controllers\BaseController / APIBaseController
  Models/                           # Eloquent (persistence for the domain)
  DataTables/                       # extend BaseDataTable — reconciliation/exceptions/tx list views
  Policies/                         # extend BasePolicy — RBAC on every privileged op
  Transformers/                     # extend Fractal Transformer — API + webhook payloads
  # domain namespaces added alongside the standard folders:
  Core/                             # owns all money + state (services, not controllers)
    Intents/ References/ Wallets/ Ledger/ Settlement/ Issuers/ Merchants/
  Contracts/                        # <-- THE SEAM: language-neutral DTOs + AdapterGateway interface
    Dto/  AdapterGateway.php  Drivers/{InProcessDriver, RemoteHttpDriver}
  Adapters/                         # <-- future NestJS service; PHP mocks today
    Mock/{RealtimeApi, Webhook, SftpBatch}/
```
Register via `module.json` + the `modules` table (`enabled=1`). Scaffold with
`php artisan make:module Gateway PaymentIntent --no-interaction` then reshape to the above; match sibling
conventions rather than inventing structure (root rule: don't create new base folders without approval —
`Core/`, `Contracts/`, `Adapters/` are the only additions, and they are domain namespaces, not app-root
folders).

## The strangler seam (so the adapter layer can become NestJS later, zero core rewrite)

1. **Adapters import ONLY `Corals\Modules\Gateway\Contracts\*`.** Never `Core\*`, never `Models\*`, never
   Eloquent, never a controller. An adapter is a pure translator: network-native ⇄ contract DTO.
2. **All adapter⇄core traffic is the versioned contract** (`contracts/`), DTOs over queue / internal
   call — never in-process reach into a core service.
3. **Core codes against `AdapterGateway`** (driver-based). Monolith now = `InProcessDriver`; extraction =
   flip to `RemoteHttpDriver` pointed at the NestJS service. DTOs, idempotency, replay cache, and ledger
   are untouched by the swap — that invariance IS the migration guarantee.

**Enforce with Deptrac** (framework-agnostic layer rules), not a runtime test. Layers: `Adapters`,
`Contracts`, `Core`, `Http`, `Models`. Allowed: `Adapters → Contracts`; `Http → Core → Models`;
`Core → Contracts`. Forbidden (fail CI): `Adapters → {Core, Models, Http}`. `/gateway:add-adapter`
scaffolds the adapter and asserts the Deptrac ruleset is present.

## Hard boundaries specific to this module

- **The Gateway ledger is the sole money-of-record. It NEVER routes balances through `corals/payment`
  or `corals/payment-stripe`.** Those modules serve a different concern (Stripe checkout/subscriptions
  for the surrounding platform). Two money subsystems coexist; they must not touch each other's books.
- **Money moves only through explicit service classes inside a DB transaction.** The `Actions`/`Filters`
  hook bus is fine for non-critical fanout (`Actions::dispatch('gateway.payment.confirmed', […])` →
  notifications, activity log, webhooks) but a ledger posting or state transition NEVER flows through a
  priority-ordered `do_filter` pipeline.
- **Overdraft guard (engine-level):** every wallet debit is
  `UPDATE pos_wallets SET balance = balance - :amt WHERE id = :id AND balance >= :amt`; **0 rows
  affected ⇒ insufficient funds ⇒ decline**, no partial write. Postgres CHECK (`balance >= 0`) is the
  backstop.
- **Idempotency + replay live in Core, not adapters.** `network_txn_id` UNIQUE; `Idempotency-Key` on
  mutating endpoints returns the stored response on replay — never a second posting.
- **`corals/*` modules are pinned `^10.x` on Laravel 13** — before wiring any of them into a money path,
  run a compatibility smoke test.

## Tests

- **PHPUnit only** (root rule). Use `php artisan make:test --phpunit {name}`; convert any Pest to PHPUnit.
- Every ledger posting ships with: balanced-posting test (debits == credits), wallet non-negative test,
  idempotency test, and drift test (wallet balance == its net ledger position).
- Tests use the real database (per `phpunit.xml`) — Postgres here.

## Guardrails for Claude Code (module)

DO: edit `contracts/` first; add networks via `/gateway:add-adapter`; add endpoints via
`/gateway:add-endpoint`; add money effects via `/gateway:add-ledger-posting`; keep adapters pure
translators; use `search-docs` before Laravel/package changes (root rule).

DON'T: import Eloquent/`Core`/`Models` inside `Adapters/`; use floats for money; credit an issuer without
a balanced posting; let `validate` skip the wallet check; route gateway money through `corals/payment`;
reintroduce a "settle-then-credit" flow (we are prepaid → credit on confirm); create new app-root folders.
