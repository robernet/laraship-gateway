# Settlement & Reconciliation (prepaid model)

Two money flows, opposite directions, both fully ledgered:

1. **Top-up (IN):** POS pre-funds its wallet via SPEI/CLABE. Credits `pos_wallet`.
2. **Payout (OUT):** gateway settles accrued `issuer_payable` to the issuer via SPEI. Debits
   `issuer_payable`.

The customer's physical cash never enters the gateway — it stays at the store and is what the store uses
to replenish its wallet. This is why the issuer can be credited **on confirm** with no credit risk.

## Top-up ingestion & matching
- POS sends MXN via SPEI to a gateway CLABE with a reference tied to `pos_wallet`.
- Inbound SPEI notifications create `wallet_top_ups(status=pending)`.
- Matching: `spei_ref` / CLABE origin → `pos_wallet`. Matched → apply (ledger credit, `status=applied`).
- Unmatched inbound funds → `reconciliation_exceptions(type=orphan_topup)` (never auto-credit an
  unmatched deposit).

## Confirmed-collection posting (recap)
```
debit  pos_wallet(store)        amount
credit issuer_payable(issuer)   amount - commission - fee
credit network_commission       commission
credit gateway_fee              fee
```
Fees/commissions are modeled from day one so the issuer sees correct **net** and reconciliation never has
to reverse-engineer them. Whether the issuer is shown gross-with-deductions or net is a reporting choice;
the ledger always carries the split.

## Settlement OUT to issuers
- Periodic (per issuer schedule): sum `issuer_payable` for the period → `settlements` row → SPEI payout →
  posting debits `issuer_payable`, credits a `settlement_clearing`/bank account.
- `settlement.completed` webhook fires; intents move to SETTLED as their payable is cleared.

## Network settlement reconciliation
- Real-time / webhook networks: confirms are already booked; the network's daily remittance/report is
  matched against booked confirms (`transactions.network_txn_id`).
- **Batch networks:** the daily layout file IS the source of confirms (`IngestSettlementBatch`); matching
  reconciles file totals vs booked totals and flags row-level gaps.
- Automated matching with a tolerance; anything outside tolerance → exceptions queue.

## Exceptions queue (Laraship admin case workflow)
Types: `unmatched_confirm`, `amount_mismatch`, `duplicate`, `orphan_topup`, `negative_drift`. Each has an
assignee, investigation notes, and a resolution that itself may generate a corrective ledger posting.

## Finality & reversal
- **Finality = on confirm** (prepaid removes credit risk). The issuer is credited immediately; the
  transaction is authoritative at CONFIRMED.
- **No cash clawback.** A **void** within the configured window is a *balanced corrective posting*
  (credit wallet back / debit issuer), dual-controlled above a threshold, fully audited.
- Post-FINALIZED corrections only via the exceptions queue with an explicit adjustment posting — never a
  silent status flip.

## Double-entry integrity checks
- Continuous invariant: for the whole ledger, `sum(debits) == sum(credits)`; per `posting_id` too.
- `pos_wallet.balance` must always equal its net ledger position (debits/credits against that wallet
  account); a mismatch raises `negative_drift`/drift alert.
- Daily close job asserts these and refuses to advance a period with open critical exceptions.
