# State Machines

Every transition is explicit and guarded. No ad-hoc status flips. Guards run inside the DB transaction
that performs the associated ledger effect, so state and money move together or not at all.

## Payment Intent

```
CREATED ──activate──▶ ACTIVE ──first CONFIRMED txn──▶ PAID_PENDING_SETTLEMENT ──settle──▶ SETTLED
   │                    │                                       │
   │                    ├── expiry ──▶ EXPIRED                  └── (reusable: stays here,
   │                    └── cancel ──▶ CANCELED                     accrues more txns)
   └── cancel ──▶ CANCELED
```

- **CREATED → ACTIVE:** references generated + signed; instrument is now payable.
- **ACTIVE → PAID_PENDING_SETTLEMENT:** first `CONFIRMED` transaction.
  - `one_time`: intent is now closed to new successes.
  - `reusable`: intent remains payable; state reflects "has ≥1 confirmed collection." New confirms keep
    landing here until settled.
- **→ SETTLED:** issuer payout for the accrued amount completes and reconciles.
- **→ EXPIRED:** `expires_at` passed with no confirmed payment (or reusable with none since last window).
- **→ CANCELED:** issuer voids the intent before any confirmed payment.

Guards: cannot activate without valid signed references; cannot confirm against `EXPIRED`/`CANCELED`;
cannot exceed `max_payments`; over/underpayment handled per policy before a CONFIRMED is allowed.

## Transaction (one collection attempt)

```
INITIATED ──validate ok──▶ AUTHORIZED ──confirm──▶ CONFIRMED ──settlement batch──▶ FINALIZED
    │                          │                       │
    │  wallet check /          │  reserve released,    │  (void window)
    │  reservation             │  wallet DEBITED,       └── void ──▶ VOIDED
    │                          │  issuer CREDITED
    └── decline ──▶ (no txn row persists as VOIDED; declines are logged, not transactions)
```

- **INITIATED → AUTHORIZED (`/v1/cash/validate`):** verify signature + not-expired + replay-check;
  resolve MID→merchant→intent; check `pos_wallet.available >= amount_attempt`; **reserve** the amount
  (`reserved += amount`). For batch/offline networks there is no validate → the transaction begins at
  confirm and the reservation step is skipped (see finality note).
- **AUTHORIZED → CONFIRMED (`/v1/cash/confirm`):** idempotent on `network_txn_id`; inside one DB tx:
  release reservation, perform the **canonical double-entry posting** (debit wallet / credit issuer +
  commission + fee), set `confirmed_at`. **This is the point of finality** (prepaid model). Fire
  `payment.confirmed` webhook.
- **CONFIRMED → FINALIZED:** the network's settlement batch/remittance matches the confirm during
  reconciliation. Booking-level closure; no money moves for the issuer (already credited on confirm).
- **CONFIRMED → VOIDED (void window only):** correction path. Reverses the posting with an equal-and-
  opposite balanced posting (credit wallet back / debit issuer). **Cash is never clawed back**; a void is
  a ledger correction + issuer-side adjustment, valid only within the configured window and blocked once
  FINALIZED unless routed through `reconciliation_exceptions`.

## Amount policies at confirm

- **fixed:** `amount_paid` must equal `amount`. Mismatch → decline (or exception if already collected).
- **variable:** `min <= amount_paid <= max`. If `allow_partial`, multiple confirms accrue against the
  intent; each is its own `transaction` and its own posting; `payment.credited` fires per partial.
- **overpay/underpay:** resolved by `overpay_policy`/`underpay_policy` (reject | accept | accept-and-flag)
  before a CONFIRMED is permitted; flagged cases open a `reconciliation_exception`.

## Idempotency & duplicates (cross-cutting)

- Every confirm carries `network_txn_id` (UNIQUE) + `idempotency_key`. Replaying returns the stored
  response, never a second posting.
- `one_time` reference: DB partial-unique guarantees at most one `CONFIRMED` transaction.
- `reusable` reference: many CONFIRMED allowed; duplicates blocked solely by `network_txn_id` uniqueness.
