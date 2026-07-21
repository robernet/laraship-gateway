# Security & Antifraud

## Identity & transport
- **POS/network ↔ gateway:** mTLS or short-TTL signed JWT per network/terminal. Terminal keys rotate;
  compromised terminal keys are revocable without downing the network.
- **Issuer ↔ gateway:** API keys/OAuth client creds; issuer webhooks are signed (HMAC) + replay-protected.
- **Internal seam (core ↔ adapter service, post-extraction):** mTLS + contract-version pinning.

## Keys
- All signing/verification keys via **KMS/HSM**; store only handles (`secret_ref`), never raw material.
- Per-merchant QR keys, versioned by `kid`, rotatable with an overlap window (see reference-qr-spec).
- Encryption in transit (TLS 1.2+) and at rest (DB/KMS envelope encryption for sensitive columns).

## Anti-replay & idempotency (core-owned)
- Nonce + timestamp checked against a Redis replay cache (TTL ≥ instrument validity).
- Idempotency keys on all mutating ops; `network_txn_id` UNIQUE. See state-machines + data-model.
- Offline/batch networks: replay defense degrades to `network_txn_id` uniqueness + reconciliation.

## Velocity & anomaly controls
- Rate limits + velocity checks keyed by **MID / store / terminal / ref-prefix** (per-minute, per-hour,
  per-day). Prepaid model also gives a natural cap: a store can't collect beyond its wallet.
- Anomaly scoring on store/terminal behavior (sudden volume spikes, off-hours, amount outliers,
  repeated near-limit variable amounts).
- **Step-up** for high-amount collections (secondary verification / manual review threshold).
- Monitoring + alerting + a Laraship-admin **case workflow** (BaseDataTable + BasePolicy) feeding `reconciliation_exceptions`.

## RBAC & audit
- Strict RBAC on all back-office and privileged ops; least privilege for support/ops roles.
- **Tamper-evident audit log:** append-only, each row includes `prev_hash` and `row_hash =
  H(prev_hash || canonical(row))` forming a hash chain; periodic checkpoints anchored/exported.
  Any edit/delete breaks the chain and is detectable.
- WAF + IP allowlists for network/terminal ingress; egress controls for outbound webhooks/SPEI.

## Threat → control matrix

| Threat                     | Control |
|----------------------------|---------|
| Forged / modified QR/barcode | Signed QR (`sig`+`kid`) + authoritative server re-validation; never trust payload content alone |
| Replay of a valid instrument | Nonce + timestamp + Redis replay cache; `network_txn_id` uniqueness |
| Reference enumeration       | High-entropy tokens, MOD 97-10 check, MID absent from human ref, rate limits on validate, decline-noise |
| Store insider fraud         | Prepaid wallet caps exposure; velocity + anomaly scoring per terminal; void collusion controls below |
| Void collusion              | Time-boxed void window, dual-control above threshold, void events audited, post-FINALIZED voids only via exception queue |
| Issuer compromise           | Scoped API creds, signed webhooks, anomaly detection on intent creation bursts, per-issuer limits |
| Overpayment / mismatch fraud | Policy checks at confirm, flagged confirms open exceptions, reconciliation catches drift |
| Adapter/network spoofing    | mTLS per network, contract-version pinning, signature on inbound confirms |

## Compliance surface (prepaid = stored value)

Because POS wallets hold prepaid balances, the operator is handling **stored value** → **IFPE / Ley
Fintech** territory (CNBV authorization), plus **PLD/AML** obligations (cash thresholds & reporting under
the Ley Antilavado), **LFPDPPP** for personal data, and **CONDUSEF** for user protection. Treat these as
design inputs: KYC/KYB on issuers and POS operators, transaction monitoring with threshold reporting,
retention + auditability of the ledger, and clear finality/void policies. This is not legal advice —
validate the specific regime (IFPE vs. a lighter structure) with counsel before go-live.
