# Build Backlog — Payment Gateway

Execution layer for `ROADMAP.md`. The roadmap says *what* each phase achieves and *why*; this file says
*what to do next*, as ordered tickets you can hand to Claude Code one at a time.

Assumes the config is integrated and Phase 0 (Steps 1–4 of `GETTING_STARTED.md`) is done.

---

## How to use this

**One ticket per session.** Open Claude Code, paste the ticket's *Do* block, let it work, then verify
against *Done when*. Don't batch tickets — the invariants are easier to keep when each change is small
and independently verified.

**Definition of done (every ticket, no exceptions):**

```
Pint (style) → Larastan (static) → Deptrac (seam) → PHPUnit (Postgres) → composer audit
```

If Deptrac goes red, an adapter reached into `Core`/`Models` — fix the seam, never suppress the rule.

**Ticket format:** `ID · title · depends on · Do · Done when`. IDs are stable; use them in commit
messages (`GW-104: wallet debit guard`).

**Contract-first is not optional.** Any ticket touching an API edits `contracts/openapi.yaml` or
`asyncapi.yaml` *before* code.

---

## Phase 0 — Close-out (do these first if not already green)

**GW-001 · Reconcile `module.json` against Laraship's generator**
*Depends on:* nothing
*Do:* `php artisan make:module Gateway PaymentIntent --no-interaction`, then ask Claude Code to merge the
generated manifest with the one shipped in the config — keep Laraship's exact schema/keys, carry over our
alias, description, dependencies, providers. Reshape to the layout in `Corals/modules/Gateway/CLAUDE.md`.
*Done when:* `php artisan corals:modules` shows Gateway enabled; app boots; `route:list` clean.

**GW-002 · Author the contracts**
*Depends on:* GW-001
*Do:* Ask Claude Code to author `contracts/openapi.yaml` (Issuer API + POS API + webhook payloads) and
`asyncapi.yaml` (`ValidateCollection`, `ConfirmCollection`, `IngestSettlementBatch` + results) from
`docs/adapter-contract.md` and `docs/architecture.md`. Money fields integer centavos. `contract_v: 1`.
*Done when:* both files lint; every DTO in `docs/adapter-contract.md` has a schema.

**GW-003 · Verify the L10-structure claim**
*Depends on:* nothing
*Do:* Check whether the app still uses the Laravel 10 skeleton (`app/Http/Kernel.php`, no
`bootstrap/app.php`). Correct that section of `CLAUDE.md`/`AGENTS.md` if it migrated.
*Done when:* the agent files match reality.

**GW-004 · `corals/*` compatibility smoke test**
*Depends on:* nothing
*Do:* Exercise the `^10.x` corals modules on Laravel 13 + Postgres. Log anything broken.
*Done when:* known-good list documented; nothing broken sits on a money path.

---

## Phase 1 — Money spine (`GW-1xx`)

The crown jewel. Nothing else matters if this is wrong.

**GW-101 · Schema: merchants, issuers, MID registry, keys**
*Do:* Migrations + models for `issuers`, `merchants` (`mid CHAR(9)` UNIQUE), `merchant_keys`
(`kid`, `alg`, `secret_ref`, state, rotation dates). Follow `docs/data-model.md`.
*Done when:* migrations run on Postgres; MID uniqueness enforced at DB level; factories exist.

**GW-102 · Schema: pos_wallets + wallet_top_ups**
*Depends on:* GW-101
*Do:* `pos_wallets` with `balance_centavos BIGINT`, `reserved_centavos BIGINT`, generated
`available_centavos`, **`CHECK (balance_centavos >= 0)`**; `wallet_top_ups` with SPEI ref + status.
*Done when:* a direct SQL attempt to set a negative balance is rejected by the CHECK.

**GW-103 · Schema: ledger_entries (append-only)**
*Depends on:* GW-102
*Do:* `ledger_entries` per `docs/data-model.md` — `posting_id`, `account_type`, `account_ref`,
`direction`, `amount_centavos`, nullable refs. No update/delete path in the model.
*Done when:* schema matches the doc; model exposes no mutation of existing rows.

**GW-104 · Posting: `topup_applied`**
*Depends on:* GW-103
*Do:* `/gateway:add-ledger-posting topup_applied`
*Done when:* the four invariant tests pass (balanced, non-negative, idempotent, no drift).

**GW-105 · Posting: `confirmed_collection`**
*Depends on:* GW-104
*Do:* `/gateway:add-ledger-posting confirmed_collection` — debit wallet, credit issuer_payable +
commission + fee.
*Done when:* four invariant tests pass; a debit exceeding balance writes **nothing**; concurrent-debit
test proves the conditional UPDATE holds under parallelism.

**GW-106 · Posting: `void_reversal`**
*Depends on:* GW-105
*Do:* `/gateway:add-ledger-posting void_reversal`
*Done when:* it is the exact equal-and-opposite of the original; blocked post-FINALIZED except via the
exceptions path.

**GW-107 · Tamper-evident audit log**
*Depends on:* GW-103
*Do:* Hash-chained `audit_log` (`prev_hash`, `row_hash`), written for every privileged/money action.
*Done when:* a test that mutates a historical row detects a broken chain.

**GW-108 · Daily-close integrity job**
*Depends on:* GW-105
*Do:* Scheduled job asserting global `sum(debits)==sum(credits)` and per-wallet ledger-vs-balance
agreement; opens a `negative_drift` exception on mismatch.
*Done when:* job passes on seeded data and fails loudly on injected drift.

> **Phase 1 exit:** no code path can overdraw a wallet, create an unbalanced posting, or mutate history.

---

## Phase 2 — Instruments (`GW-2xx`)

**GW-201 · Schema: payment_intents + payment_references**
*Do:* Per `docs/data-model.md`: modes, `amount_policy` JSON, expiry, `max_payments`, over/under policy;
references with token, human ref, barcode payload, QR payload, `kid`, nonce.
*Done when:* migrations run; one-time-ref single-success constraint exists (partial unique).

**GW-202 · Reference generation (both mapping strategies)**
*Depends on:* GW-201
*Do:* `Core/References`: deterministic (`f(invoice_id, issuer_secret) mod 1e10`) and stored-random;
human ref = 10-digit token + **MOD 97-10** check digits. **No MID in the human reference.**
*Done when:* check digits catch single-digit and transposition errors in tests; tokens non-sequential.

**GW-203 · Barcode payload (Code128-C)**
*Depends on:* GW-202
*Do:* `MID(9) + token(10) + checksum(2)`.
*Done when:* round-trips; checksum validates.

**GW-204 · Signed QR + verification**
*Depends on:* GW-202
*Do:* QR payload per `docs/reference-qr-spec.md`; HMAC-SHA256 signing via `kid`; verification that
**re-reads policy/state/amounts from the DB**, never trusting the payload.
*Done when:* tampered payload rejected; QR amount disagreeing with the DB is rejected, not honored.

**GW-205 · Key rotation with overlap**
*Depends on:* GW-204
*Do:* Activate new `kid`, dual-verify during overlap, retire after `retire_after`.
*Done when:* instruments signed with the retiring key still verify until expiry.

**GW-206 · Replay cache**
*Depends on:* GW-204
*Do:* Redis nonce+timestamp cache, TTL ≥ instrument validity.
*Done when:* second sighting of a nonce within TTL is rejected.

---

## Phase 3 — Issuer API + portal (`GW-3xx`)

**GW-301 · `POST /v1/payment-intents`**
*Do:* `/gateway:add-endpoint POST /v1/payment-intents` — all modes (one-time/reusable,
fixed/variable, partial), expiry, `max_payments`, over/under policy.
*Done when:* happy path, idempotent replay, validation failure, authz failure all tested.

**GW-302 · Status endpoints**
*Depends on:* GW-301
*Do:* `GET /v1/payment-intents/{id}`, `GET /v1/invoices/{invoice_id}/status`.
*Done when:* Hashids only, no raw PKs; Transformer output matches OpenAPI.

**GW-303 · Transactional outbox**
*Depends on:* GW-105
*Do:* Outbox table + dispatcher; events written in the same transaction as the state change.
*Done when:* a crash between commit and delivery loses no event (test with a forced failure).

**GW-304 · Signed outbound webhooks**
*Depends on:* GW-303
*Do:* `payment.confirmed/credited/expired/voided`, `settlement.completed`; HMAC signature, timestamp,
nonce, retries with backoff.
*Done when:* receiver can verify signature and detect replay; retry/backoff tested.

**GW-305 · Sanctum auth + scopes + rate limits**
*Do:* Issuer credentials, scoped abilities, short TTL; throttle on all `/v1` routes.
*Done when:* unauthorized/over-scope calls rejected; `validate` rate-limited (enumeration surface).

**GW-306 · Issuer portal (Vue 3 SPA)**
*Depends on:* GW-302
*Do:* Create intents, track invoices, download settlement reports, manage API keys + webhook endpoints.
*Done when:* an issuer completes the full loop without touching the API directly.

**GW-307 · Developer portal + sandbox**
*Depends on:* GW-306
*Do:* OpenAPI docs, key management, sandbox environment, webhook tester.
*Done when:* a third party can integrate from the portal alone.

---

## Phase 4 — POS API + adapters (`GW-4xx`)

**GW-401 · `POST /v1/cash/validate`**
*Depends on:* GW-204, GW-102
*Do:* `/gateway:add-endpoint POST /v1/cash/validate` — verify instrument, resolve MID→merchant→intent,
**check wallet available ≥ amount_attempt**, reserve.
*Done when:* insufficient balance declines; expired/tampered/replayed instruments decline; reservation
released on timeout.

**GW-402 · `POST /v1/cash/confirm`**
*Depends on:* GW-401, GW-105
*Do:* `/gateway:add-endpoint POST /v1/cash/confirm` — idempotent on `network_txn_id`; release
reservation; canonical posting; finality; emit `payment.confirmed`.
*Done when:* duplicate `network_txn_id` returns the stored response with **no second posting**;
one-time ref confirms exactly once under concurrent attempts.

**GW-403 · `POST /v1/cash/batch-confirm`**
*Depends on:* GW-402
*Do:* Batch endpoint fanning rows into idempotent confirms with `finality_hint = batch`, no reservation.
*Done when:* malformed rows land in `reconciliation_exceptions` without aborting the batch.

**GW-404 · Mock adapter: real-time API**
*Depends on:* GW-402
*Do:* `/gateway:add-adapter mock-realtime realtime`
*Done when:* Deptrac passes; contract-conformance test passes; injected failures (timeout, duplicate,
tamper) behave correctly.

**GW-405 · Mock adapter: webhook**
*Depends on:* GW-402
*Do:* `/gateway:add-adapter mock-webhook webhook`
*Done when:* signature verification enforced; unsigned/replayed pushes rejected.

**GW-406 · Mock adapter: SFTP batch**
*Depends on:* GW-403
*Do:* `/gateway:add-adapter mock-sftp sftp` — poller + layout fixture + malformed-row test.
*Done when:* a dropped file produces confirms; one bad row exceptions out, batch completes.

**GW-407 · Terminal auth (mTLS / short-TTL JWT)**
*Depends on:* GW-402
*Do:* Per-terminal credentials, individually revocable.
*Done when:* revoked terminal is rejected immediately.

**GW-408 · Reference POS simulator**
*Depends on:* GW-404
*Do:* Lightweight UI exercising validate→confirm, fixed/variable/partial.
*Done when:* a full collection can be demoed end-to-end.

> **Phase 4 exit:** a simulated collection draws the wallet, credits the issuer, fires the webhook, and
> cannot be duplicated — across all three adapter archetypes.

---

## Phase 5 — Settlement & reconciliation (`GW-5xx`)

**GW-501 · Top-up ingestion + matching**
*Depends on:* GW-104
*Do:* Inbound SPEI notification → `wallet_top_ups(pending)` → match by `spei_ref`/CLABE → apply.
*Done when:* unmatched deposits open `orphan_topup`, never auto-credit.

**GW-502 · Issuer settlement (payout OUT)**
*Depends on:* GW-105
*Do:* Period aggregation of `issuer_payable` → `settlements` row → SPEI payout → posting; fire
`settlement.completed`.
*Done when:* full cycle (top-up → collections → payout) reconciles to zero drift.

**GW-503 · Network reconciliation + matching engine**
*Depends on:* GW-403
*Do:* Match network remittances/files against booked confirms with tolerance.
*Done when:* mismatches outside tolerance open typed exceptions.

**GW-504 · Exceptions queue + case workflow (admin)**
*Depends on:* GW-503
*Do:* Laraship `BaseDataTable` + `BasePolicy` UI: assign, investigate, resolve; resolutions that move
money generate corrective postings.
*Done when:* an exception can be worked end-to-end and its corrective posting balances.

**GW-505 · Void flow with dual control**
*Depends on:* GW-106
*Do:* Time-boxed void window; dual approval above threshold; every void audited.
*Done when:* single-actor void above threshold is impossible.

**GW-506 · Ops console: wallets, top-ups, transactions**
*Depends on:* GW-504
*Do:* Admin views for wallet balances, top-up history, transaction search, audit browser.
*Done when:* ops can answer "where is this payment?" without SQL.

---

## Phases 6–8 — Hardening, compliance, production (`GW-6xx/7xx/8xx`)

Lighter granularity; expand into tickets when you reach them.

**GW-6xx Hardening:** velocity limits by MID/store/terminal/ref-prefix · anomaly scoring · step-up for
high amounts · WAF + IP allowlists · ASVS L2/L3 self-assessment · load + chaos tests on the confirm hot
path · **external penetration test**.

**GW-7xx Compliance:** KYC/KYB onboarding for issuers and POS operators · AML transaction monitoring +
threshold reporting · data-protection controls (minimization, access logs, DSAR) · retention policy ·
IFPE authorization prep with counsel · SOC 2 / ISO 27001 groundwork.

**GW-8xx Production/SRE:** observability + SLOs · PgBouncer + read replicas · partition `ledger_entries`
by period · backups + **PITR restore drill** · DR region · runbooks · on-call · blue-green deploys ·
go-live checklist.

---

## Phase 9–10 — Real networks, then extraction

**GW-9xx:** replace each mock with a real network adapter (OXXO/Paynet/PayCash/Cashi-style, then
ClubPago layouts) via `/gateway:add-adapter`. Same contract, richer translator. Document every
concession in the adapter; never bend the core.
*Exit:* one real network settles end-to-end in production.

**GW-10xx:** stand up the NestJS + TS adapter service against the same contract; flip `AdapterGateway`
to `RemoteHttpDriver`; migrate networks one at a time.
*Exit:* a network runs through the TS service with the PHP core untouched.

---

## Parallel track — start now, not at Phase 7

These have the longest lead times in the whole programme and no amount of coding speed compensates:

| Workstream | Start | Why now |
|---|---|---|
| **SPEI-connected bank / regulated PSP** | Today | Settlement rails gate Phase 5. Onboarding + due diligence takes months. |
| **IFPE / Ley Fintech counsel** | Today | Prepaid balances = stored value. Authorization shapes the corporate structure, not just the code. |
| **AML/compliance officer** | Before Phase 5 | KYC/KYB and monitoring design must exist before you hold real balances. |
| **Network commercial agreements** | Before Phase 9 | Real layouts and commission terms are contractual, often NDA'd. |
| **External pen-test vendor** | Book at Phase 5 | Lead times; Phase 6 exit depends on it. |

---

## Critical path

```
GW-001/002 → GW-101→108 (money spine) → GW-201→206 (instruments)
    → GW-401/402 (validate/confirm) → GW-404/405/406 (adapters)
    → GW-501/502/503 (settlement + recon) → GW-6xx hardening → go-live
```

Everything else (portals, dev portal, simulator, ops console) is parallelizable once its dependency
lands. If you have limited time in a given week, spend it on the critical path — a beautiful issuer
portal on top of an unproven ledger is negative progress.

---

## Suggested first three sessions

1. **GW-001 + GW-002** — module registered, contracts authored. (Closes Phase 0.)
2. **GW-101 + GW-102** — schema through wallets, with the CHECK constraint proven.
3. **GW-104 + GW-105** — the two postings and their invariant tests. **This is the moment the system
   becomes real**: after this, money can move and cannot be lost.
