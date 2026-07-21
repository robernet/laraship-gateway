# Data Model

All amounts are **integer centavos (`BIGINT`)**. Public IDs are **Hashids** over `BIGINT` PKs.
Timestamps are UTC. Every mutating table carries `created_at`/`updated_at` and, where relevant, an
append-only audit trail (see `security-antifraud.md`).

## Core tables

### `issuers`
Tenant billers who collect. `id`, `public_id`, `name`, `settlement_clabe`, `status`, `webhook_url`,
`webhook_secret`, `finality_policy` (default `on_confirm`), timestamps.

### `merchants`  (the MID registry)
The routing identity embedded in machine-read carriers. `id`, `mid` (`CHAR(9)`, UNIQUE, 9 decimal
digits), `issuer_id`, `signing_key_current_kid`, `status`. MID identifies/routes only; authenticity is
enforced by signatures + server checks (see `reference-qr-spec.md`).

### `merchant_keys`
Versioned signing keys for QR/HMAC/ECDSA. `id`, `merchant_id`, `kid`, `alg` (`HS256`|`ES256`),
`secret_ref` (KMS/HSM handle — never the raw key), `state` (`active`|`retiring`|`revoked`),
`activated_at`, `retire_after`. Supports rotation without invalidating in-flight instruments.

### `pos_wallets`   ← prepaid balances
One per collection point (store/terminal group). `id`, `public_id`, `network_id`, `external_store_id`,
`balance_centavos` (`BIGINT`, CHECK `>= 0`), `reserved_centavos` (`BIGINT`, CHECK `>= 0`),
`available_centavos` GENERATED `= balance - reserved`, `currency` (`MXN`), `status`.

> **Overdraft invariant:** every debit is a conditional update
> `UPDATE pos_wallets SET balance = balance - :amt WHERE id = :id AND balance >= :amt` returning
> affected rows; 0 rows ⇒ insufficient funds ⇒ decline. Postgres CHECK is the backstop.

### `wallet_top_ups`
POS pre-funding events (SPEI/CLABE inbound). `id`, `pos_wallet_id`, `amount_centavos`, `spei_ref`,
`clabe_origin`, `status` (`pending`|`applied`|`rejected`), `applied_at`. Applying a top-up is itself a
ledger posting (credit wallet).

### `payment_intents`
`id`, `public_id`, `issuer_id`, `merchant_id`, `invoice_id`, `mode`
(`one_time`|`reusable`), `amount_policy` (JSON: `{type: fixed|variable, amount?, min?, max?,
allow_partial: bool}`), `mapping_strategy` (`deterministic`|`stored`), `state`
(`CREATED`|`ACTIVE`|`PAID_PENDING_SETTLEMENT`|`SETTLED`|`EXPIRED`|`CANCELED`), `expires_at`,
`max_payments`, `overpay_policy`, `underpay_policy`, timestamps. UNIQUE(`issuer_id`,`invoice_id`) unless
reusable multi-invoice is explicitly configured.

### `payment_references`
The instruments generated for an intent. `id`, `payment_intent_id`, `reference_token`, `human_reference`
(cashier-typable, check-digited), `barcode_payload`, `qr_payload` (signed), `kid`, `nonce`, `expires_at`,
`status`. Deterministic mapping: token derived from `invoice_id + issuer_secret + checksum` (regenerable).
Stored mapping: random token persisted here (invoice_id ↔ ref).

### `transactions`   (one per collection attempt that reaches confirm)
`id`, `public_id`, `payment_reference_id`, `pos_wallet_id`, `network_id`, `network_txn_id` (UNIQUE),
`idempotency_key` (UNIQUE), `amount_centavos`, `state`
(`INITIATED`|`AUTHORIZED`|`CONFIRMED`|`FINALIZED`|`VOIDED`), `is_partial`, `collected_at`,
`confirmed_at`, `finality` (`on_confirm`). One-time refs: at most one `CONFIRMED`. Reusable refs: many,
but `network_txn_id` UNIQUE blocks duplicates.

### `ledger_entries`   ← double-entry, append-only
`id`, `posting_id` (groups the two-plus legs of one posting), `account_type`
(`pos_wallet`|`issuer_payable`|`network_commission`|`gateway_fee`|`suspense`), `account_ref`,
`direction` (`debit`|`credit`), `amount_centavos`, `transaction_id?`, `top_up_id?`, `settlement_id?`,
`created_at`. **INVARIANT: for every `posting_id`, sum(debits) == sum(credits).** Enforced in a DB
transaction + asserted in `/add-ledger-posting` tests.

Canonical confirmed-collection posting:
```
posting P:
  debit  pos_wallet(store)         amount
  credit issuer_payable(issuer)    amount - commission - fee
  credit network_commission        commission
  credit gateway_fee               fee
  (sum debits == sum credits)
```

### `settlements`
Payouts OUT to issuers (SPEI). `id`, `issuer_id`, `period`, `gross_centavos`, `commission_centavos`,
`fee_centavos`, `net_centavos`, `spei_ref`, `status`, `reconciled_at`. Draws down `issuer_payable`.

### `reconciliation_exceptions`
`id`, `type` (`unmatched_confirm`|`amount_mismatch`|`duplicate`|`orphan_topup`|`negative_drift`),
`refs` (JSON), `state` (`open`|`investigating`|`resolved`), `assignee`, `resolution`, timestamps.
Surfaced in the Laraship admin (BaseDataTable + BaseController) as the exceptions queue / case workflow.

### Infrastructure tables
`idempotency_keys` (key, scope, request_hash, response_snapshot, expires_at) ·
`replay_nonces` (nonce, mid, seen_at, ttl — mirrored in Redis) ·
`webhook_deliveries` (issuer_id, event, payload, signature, attempts, next_retry_at, delivered_at) ·
`audit_log` (append-only, hash-chained; see security doc) ·
`network_adapters` (network_id, archetype, config, contract_version, enabled).

## ID strategy

- Internal PK: `BIGINT` autoincrement.
- Public/API-facing: **Hashids** (opaque, non-enumerable) — consistent with the Rentyx convention.
- `mid`: 9 decimal digits, NOT a Hashid (it is a fixed-width routing field in carriers).
- `network_txn_id` / `idempotency_key`: provided by caller/network, UNIQUE, drive duplicate prevention.
