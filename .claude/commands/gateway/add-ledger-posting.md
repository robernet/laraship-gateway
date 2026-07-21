---
description: Scaffold a balanced double-entry ledger posting with PHPUnit invariant tests (Laraship module)
argument-hint: <posting_name>  e.g. confirmed_collection | topup_applied | issuer_settlement | void_reversal
---

Read `Corals/modules/Gateway/docs/data-model.md` (ledger section) and `docs/settlement-reconciliation.md`.

Posting: `$1`

Do this:

1. Define the posting as one method in `Corals\Modules\Gateway\Core\Ledger\Postings\` that writes all
   legs inside ONE DB transaction under a shared `posting_id`.
2. Legs use **integer centavos**, `account_type` from the allowed set, correct `debit`/`credit`
   directions. Assert **sum(debits) == sum(credits)** before commit; abort the whole tx on imbalance.
3. If the posting debits a `pos_wallet`, use the conditional update
   `UPDATE pos_wallets SET balance = balance - :amt WHERE id = :id AND balance >= :amt`; treat 0 affected
   rows as insufficient funds → decline (no partial write). Postgres `CHECK (balance >= 0)` is the backstop.
4. **PHPUnit** tests (`make:test --phpunit`, real DB = Postgres):
   - balanced-posting (debits == credits);
   - wallet non-negative (a debit exceeding balance is rejected, nothing written);
   - idempotency (same idempotency key ⇒ no second posting);
   - drift (wallet balance == its net ledger position after the posting).
5. For `void_reversal`: assert it is the exact equal-and-opposite of the original posting and is blocked
   post-FINALIZED except via the exceptions path.

Summarize the legs table and the invariants covered.
