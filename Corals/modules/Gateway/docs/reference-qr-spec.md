# Reference / Barcode / QR Spec

## Design correction vs the original brief

The MID is **9 decimal digits** (capacity 1e9 merchants — future-proof). But do **not** embed the full
9-digit MID inside the *human reference a cashier types*. Real Mexican cashier flows cap the typed
reference (OXXO-style human refs are ~14 digits total); spending 9 on MID leaves too few digits for
token entropy + a checksum, which cripples anti-enumeration.

**Rule:** MID lives in the *machine-read* carriers (barcode + QR), where digits are cheap. The **human
reference carries no MID** — routing is resolved server-side from the token. This preserves the digit
budget for entropy and check digits and still lets a cashier key a payment by hand.

## Human reference (cashier-typable)

```
[ token(10) ][ check(2) ]      → 12 digits, numeric-only
```
- `token` — 10 digits: either deterministic (`derive(invoice_id, issuer_secret)` mod 1e10, regenerable)
  or a stored random 10-digit token (unique per merchant namespace).
- `check` — 2 digits, **ISO 7064 MOD 97-10** over the token (catches transpositions + single-digit slips).
- Routing: server looks up `token → merchant → MID → issuer/intent`. No MID typed by the cashier.
- Optional flag digits (expiry/mode) MAY be appended only if the target network's typed-length budget
  allows; default is to keep the human ref minimal and carry flags in the QR.

## Barcode (Code128 preferred; PDF417 optional)

```
payload = MID(9) + token(10) + checksum(2)     → 21 numeric chars, Code128-C (even length friendly)
```
- Code128-C encodes digit pairs compactly; 21 digits is well within scanner limits.
- `checksum` — MOD 97-10 over `MID+token`.
- PDF417 only where a network mandates it; same logical payload, richer error correction.

## QR payload (richer + signed)  — the authoritative instrument

Compact JSON (CBOR optional for density). Signed; never trusted on content alone.

```json
{
  "v": 1,
  "mid": "000123456",
  "ref": "0123456789",
  "amt": { "cur": "MXN", "type": "variable", "min": 5000, "max": 500000, "partial": true },
  "exp": 1739750400,
  "non": "b7f3…",              // nonce (anti-replay)
  "kid": "m123-k2",           // key id -> merchant_keys
  "sig": "…"                  // HMAC-SHA256 (v1) or ECDSA P-256 (high assurance)
}
```
- `amt.type` = `fixed` (add `amount`) | `variable` (`min`/`max`, optional `partial`). Amounts in centavos.
- `sig` covers the canonical serialization of all fields except `sig` itself.
- **Authenticity is enforced server-side**, always: verify `sig` with `kid`, check `exp`, check `non`
  against the replay cache, then re-derive/verify `ref`→intent. A valid-looking QR with a bad server
  check is rejected regardless of signature (defense in depth).

## Signing, keys, rotation

- **v1 default: HMAC-SHA256 per merchant** (`merchant_keys.secret_ref` in KMS/HSM). Cheap, fast, fine
  when the gateway is the only verifier.
- **ES256 (ECDSA P-256) optional** where third parties must verify without holding a shared secret.
- **Versioned keys via `kid`** → rotation is: activate new `kid`, dual-verify during overlap, retire old
  after `retire_after`. In-flight instruments signed with the old `kid` keep verifying until they expire.
- Keys are never stored raw; only KMS/HSM handles (`secret_ref`).

## Anti-replay

- QR/API carry `non` + timestamp; core checks against a Redis replay cache (TTL ≥ instrument validity
  window). Second sight of the same `non` within TTL → reject.
- **Offline/batch caveat:** stores collecting offline cannot do validate-before-confirm, so per-attempt
  nonce replay defense doesn't apply at collection time. For batch networks, duplicate defense shifts
  entirely to `network_txn_id` uniqueness + reconciliation. This is a documented, intentional concession.

## Invoice mapping (both supported per issuer)

- **Deterministic:** `token = f(invoice_id, issuer_secret) mod 1e10`, regenerable, no storage of the map;
  collisions handled by a per-merchant salt + uniqueness check.
- **Stored:** gateway issues a random token and persists `invoice_id ↔ token` in `payment_references`.
