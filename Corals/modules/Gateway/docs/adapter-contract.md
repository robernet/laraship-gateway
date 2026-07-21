# Adapter Contract (the migration seam)

This is the language-neutral boundary between the **core** (Laravel, permanent) and the **adapter layer**
(PHP mocks now → NestJS + TS later). Everything crossing it is a versioned DTO. Nothing crossing it is an
Eloquent model or an in-process core call. Get this right and the NestJS migration is a transport swap.

Machine-readable schemas live in `contracts/asyncapi.yaml` (events) and `contracts/openapi.yaml`
(sync REST). This doc is the human contract.

## Direction A — adapter → core (Core Ingress Contract)

The adapter translates a network's native input into one of these normalized commands and hands it to
the core. Money is integer centavos.

### `ValidateCollection`  (real-time networks only)
```json
{ "contract_v": 1, "network_id": "mock-realtime", "mid": "000123456",
  "ref": "0123456789", "amount_attempt": 25000,
  "store_id": "S-001", "terminal_id": "T-07", "request_id": "…" }
```
→ **`ValidateResult`**
```json
{ "ok": true, "intent_state": "ACTIVE", "amount_policy": { "...": "..." },
  "reservation_id": "…", "decline_reason": null }
```
Core work: verify signature/expiry/replay, resolve MID→merchant→intent, check `pos_wallet.available >=
amount_attempt`, place a reservation. `ok=false` with a `decline_reason` on insufficient funds / expired
/ tamper / policy mismatch.

### `ConfirmCollection`
```json
{ "contract_v": 1, "network_id": "mock-realtime", "mid": "000123456",
  "ref": "0123456789", "amount_paid": 25000, "is_partial": false,
  "network_txn_id": "NTX-abc-123", "idempotency_key": "…",
  "store_id": "S-001", "terminal_id": "T-07", "collected_at": 1739750000 }
```
→ **`ConfirmResult`**
```json
{ "ok": true, "transaction_public_id": "…", "auth_code": "…",
  "receipt": { "folio": "…", "amount": 25000, "ts": 1739750001 }, "error": null }
```
Core work (one DB tx): idempotency check on `network_txn_id`, release reservation, canonical double-entry
posting (debit wallet / credit issuer+commission+fee), advance state to CONFIRMED, enqueue
`payment.confirmed` webhook. Replays return the stored result — never a second posting.

### `IngestSettlementBatch`  (SFTP-batch networks)
```json
{ "contract_v": 1, "network_id": "mock-sftp", "batch_id": "B-20260714",
  "rows": [ { "network_txn_id": "…", "mid": "…", "ref": "…",
             "amount_paid": 25000, "collected_at": 1739700000 } ] }
```
→ core fans each row into an idempotent `ConfirmCollection` with `finality_hint = batch` and no prior
reservation. Row-level failures land in `reconciliation_exceptions`, never blocking the batch.

## Direction B — core → adapter (Callback Contract)

Minimal, for networks that need a response delivered back out.

- `DeliverAuthFields(network_txn_id, auth_code, receipt)` — push confirm receipt fields to a network
  that expects an async callback.
- `RequestRepoll(network_id, since)` — ask a batch adapter to re-fetch a window (recovery).

## Versioning

- Every message carries `contract_v`. The core accepts a known set of versions; adapters declare theirs
  in `network_adapters.contract_version`.
- During the NestJS migration, a PHP adapter and a TS adapter can both speak `v1` simultaneously. Breaking
  changes bump to `v2` and run side-by-side until all adapters upgrade.

## Transport (the only thing that changes at migration)

| Phase | AdapterGateway driver | ValidateCollection | ConfirmCollection | Batch |
|-------|-----------------------|--------------------|-------------------|-------|
| 1 (monolith) | `InProcessDriver` | in-proc DTO call | in-proc DTO call | queued job |
| 2 (extracted) | `RemoteHttpDriver` | HTTP/req-reply | HTTP/queue | queue + SFTP in TS |

The DTOs, idempotency, replay cache, and ledger are identical across both rows. That invariance is the
migration guarantee.

## Enforcement (so the seam can't silently rot)

- `app/Adapters/*` may import ONLY `app/Contracts/*`. Add an architecture test (e.g. Deptrac / a Pest
  arch test) asserting no `App\Core\*` or Eloquent import inside `App\Adapters\*`. `/add-adapter`
  scaffolds this test automatically.
