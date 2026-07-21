# Payment Gateway — Full Platform Development Roadmap

A complete build plan for the cash-collection payment gateway: backend, frontends, APIs, data,
security, and the delivery cycle — mapped to named industry standards. It extends the decisions already
locked in `CLAUDE.md` and `Corals/modules/Gateway/`: **prepaid POS wallets, double-entry ledger,
finality-on-confirm, the strangler seam, PostgreSQL, Laravel 13 / PHP 8.3, integer centavos, Hashids.**

Written for a solo / small team building with Claude Code. Where a phase needs an outside specialist
(security pen-test, IFPE counsel, a SPEI-connected bank/PSP), it's called out explicitly.

---

## 0. Guiding standards

Every phase is measured against these, not against "does it work":

| Area | Standard | Target |
|---|---|---|
| App security | OWASP **ASVS** + OWASP Top 10 + API Security Top 10 | ASVS **L2** platform-wide, **L3** on the money core |
| Payments posture | **PCI-DSS**-informed controls | Card data is out of scope (cash), but hold to PCI-grade key/access/audit controls |
| Key management | **NIST SP 800-57** | HSM/KMS, versioned keys, rotation |
| Identity | **NIST SP 800-63** / OAuth2 / mTLS | short-TTL tokens, MFA for admin |
| Org security | **ISO/IEC 27001** ISMS + **SOC 2 Type II** | trajectory, not day-1 |
| Engineering | **12-Factor App**, SemVer, Conventional Commits, test pyramid, TDD | enforced in CI |
| Mexico regulatory | Ley Fintech (**IFPE** via CNBV), **PLD/AML** (LFPIORPI), data-protection law (LFPDPPP/successor), **CONDUSEF**, **SPEI/CoDi** (Banxico) | validate the exact regime with counsel — prepaid balances = stored value |

**Prime directive (never traded away for a deadline):** money moves only through a balanced double-entry
posting inside one DB transaction; wallets never go negative; adapters never touch the core datastore.

---

## 1. Languages & runtime per layer

The stack is deliberately polyglot, each choice justified by fit — not preference.

| Layer | Language / runtime | Why |
|---|---|---|
| Domain core, application services, Issuer/POS APIs, admin | **PHP 8.3 + Laravel 13** (Laraship `Gateway` module) | Fastest path for you; mature ecosystem; strong transactional DB story; Sanctum/RBAC/DataTables in-house |
| Adapter layer (Phase 10) | **TypeScript + NestJS** | Async I/O fits network fan-out (real-time/webhook/SFTP); typed money domain; reuses your OpenWA muscle |
| Admin / ops console | **Vue 2 + Bootstrap 4** (Laraship theme) | Native to Laraship; DataTables-driven reconciliation UI |
| Issuer portal + Developer portal | **Vue 3 + Vite + TS** SPA (or Inertia) on the API | Greenfield surface; modern DX; no legacy constraint |
| Reference POS simulator | **Vue 3 / lightweight** | Test harness for direct-integration networks |
| Data | **PostgreSQL 16** (system of record) + **Redis 7** (cache/queue/replay) | Constraints for ledger integrity; Redis for velocity/idempotency/nonces |
| Infra | **Docker**, **Terraform** (IaC), **GitHub Actions** | Reproducible, 12-Factor, auditable change control |
| Hot-path (only if needed) | **Go** | Reserve for the confirm path only if throughput demands it later |

---

## 2. Coding layers (hexagonal / clean architecture)

The dependency rule points **inward**: outer layers depend on inner, never the reverse. Deptrac enforces
it in CI.

```
        ┌──────────────────────────────────────────────────────────────┐
        │  PRESENTATION                                                  │
        │  Http/Controllers · DataTables · Transformers · Vue frontends  │
        └───────────────────────────┬──────────────────────────────────┘
                                     │ depends on
        ┌───────────────────────────▼──────────────────────────────────┐
        │  APPLICATION  (Core/*)                                         │
        │  use-cases / services: CreateIntent, ValidateCollection,       │
        │  ConfirmCollection, Settle, Reconcile — own the DB transaction │
        └───────────────────────────┬──────────────────────────────────┘
                                     │ depends on
        ┌───────────────────────────▼──────────────────────────────────┐
        │  DOMAIN  (pure invariants — no framework)                      │
        │  Money(centavos), Reference, WalletBalance rule, state machines│
        └───────────────────────────┬──────────────────────────────────┘
                                     │ defines
        ┌───────────────────────────▼──────────────────────────────────┐
        │  PORTS  (Contracts/*)                                          │
        │  AdapterGateway interface · DTOs (Validate/Confirm/Ingest)     │
        └───────────────────────────▲──────────────────────────────────┘
                                     │ implemented by
        ┌───────────────────────────┴──────────────────────────────────┐
        │  INFRASTRUCTURE                                                │
        │  Eloquent Models · Adapters(Mock→real→NestJS) · Redis · SPEI · │
        │  KMS/HSM · queue workers · outbox                              │
        └───────────────────────────────────────────────────────────────┘
```

- **Domain** holds the rules that must never break (money is centavos, a wallet can't go negative, a
  one-time ref confirms once). No Laravel here — pure PHP, unit-testable in isolation.
- **Application (`Core/`)** orchestrates use-cases and owns the transaction boundary.
- **Ports (`Contracts/`)** are the seam. **Infrastructure (`Adapters/`, `Models/`)** plugs in behind them.
- Deptrac ruleset: `Adapters → Contracts` only; `Presentation → Application → Domain → Ports`;
  forbidden: `Adapters → {Core, Models, Http}`. A violation fails the build.

---

## 3. Platform components (the "everything")

**Backend** — the `Gateway` module: intents, references/instruments, wallets, ledger, settlement,
reconciliation, key management, audit.

**APIs** — four surfaces, all versioned (`/v1`), all contract-first (OpenAPI for REST, AsyncAPI for the
adapter seam), all rate-limited:
1. **Issuer API** — create/query payment-intents, invoice status, settlement reports, key management.
2. **POS / Network API** — `cash/validate`, `cash/confirm`, `cash/batch-confirm` (via the adapter layer).
3. **Webhooks (outbound)** — `payment.confirmed/credited/expired/voided`, `settlement.completed`, signed
   + replay-protected, delivered via an **outbox + retry** service.
4. **Admin API** — internal, RBAC-gated, powering the ops console.

**Frontends:**
- **Ops/Admin console** (Laraship Vue2/BS4 + DataTables): reconciliation, exceptions queue, case
  workflow, wallet/top-up management, manual voids (dual-control), audit browser.
- **Issuer self-service portal** (Vue3 SPA): create intents, track invoices, download settlements, manage
  API keys + webhooks.
- **Developer portal**: API keys, sandbox, OpenAPI docs, webhook tester.
- **Reference POS simulator**: exercises validate/confirm for direct-integration networks and demos.

**Async / eventing:** Horizon queue workers; **transactional outbox** for reliable webhook/event emission
(no lost events on crash); SFTP pollers for batch networks.

**Data platform:** Postgres primary + read replica(s) for reporting; Redis for replay/idempotency/velocity
and queues; object storage for batch files + generated settlement reports.

**Security services:** KMS/HSM for signing keys; secrets manager (Vault) for app credentials; IAM/RBAC.

**Observability:** structured logs, metrics, distributed traces, alerting, dashboards, SLOs.

**Compliance/ops:** KYC/KYB onboarding, AML transaction monitoring, tamper-evident audit, retention.

---

## 4. Database design & evolution

**Postgres is the system of record.** Design principles:
- **Double-entry, append-only ledger.** Postings are immutable; corrections are new balanced postings.
- **Integer centavos (`BIGINT`)** everywhere; **Hashids** for external IDs; `BIGINT` internal PKs.
- **Overdraft guard at the engine:** `UPDATE pos_wallets SET balance = balance - :amt WHERE id = :id AND
  balance >= :amt` → 0 rows ⇒ decline. `CHECK (balance >= 0)` is the backstop.
- **Constraints do the enforcing:** UNIQUE on `network_txn_id`/`idempotency_key`; partial-unique for
  one-time-ref single-success; FK integrity; exclusion constraints where useful.
- **Transactional outbox table** so webhook/adapter events are emitted atomically with the state change.

**Scale & reliability:**
- **Partition `ledger_entries` / `transactions` by period** (monthly) once volume grows — keeps the hot
  set small and archival clean.
- **PgBouncer** connection pooling; **read replicas** for reporting/reconciliation queries.
- **Backups + PITR** with explicit RPO/RTO targets; tested restore drills; DR region.
- **Daily-close job** asserts global `sum(debits)==sum(credits)` and per-wallet ledger-vs-balance
  agreement; refuses to advance a period with open critical exceptions.

**Migration discipline:** Laravel migrations, forward-only in production, reviewed like code; a column
change re-declares all prior attributes (root CLAUDE.md rule); no destructive change without a paired
backfill + rollback plan.

**Redis:** replay/nonce cache (TTL ≥ instrument validity), idempotency store, velocity counters, queue
backend. Treated as ephemeral — never the source of truth for money.

**Retention & residency:** ledger + audit retained per regulatory requirement; PII minimized and access-
logged; document data residency for LFPDPPP/successor.

---

## 5. Security locks (industry-standard, layered)

Defense in depth — each layer independently valuable.

**Identity & access**
- Issuers: OAuth2 client-credentials / scoped API keys. POS/terminals: **mTLS** or short-TTL signed JWT.
- Internal APIs: **Sanctum** tokens, short TTL. Admin: **MFA** mandatory.
- **RBAC + Segregation of Duties + dual-control** on money ops (voids, manual adjustments, key rotation,
  settlement release). Least privilege everywhere; every privileged action behind a `BasePolicy`.

**Cryptography & keys (NIST 800-57)**
- Per-merchant QR/HMAC (or ES256) signing keys in **KMS/HSM**; only handles in the DB, never raw keys.
- Versioned by `kid`, rotated with an overlap window; in-flight instruments keep verifying until expiry.
- **TLS 1.3** in transit; encryption at rest (tablespace/column) for sensitive data; envelope encryption.

**Application security (OWASP ASVS L2/L3, Top 10, API Top 10)**
- Contract-first validation (FormRequests + schema), output encoding, secure headers, CSRF on session
  surfaces, strict CORS, no mass-assignment.
- **SAST** (PHPStan/Larastan max), **DAST**, dependency scanning (`composer audit`, Dependabot), secret
  scanning in CI. **SBOM** produced per release; deps pinned via lockfiles.
- Explicit review of the pinned `corals/*` modules before any sit near the money path.

**Integrity & anti-fraud**
- **Idempotency** + **replay cache** in the core; `network_txn_id` uniqueness.
- **Tamper-evident audit log** (hash-chained: `row_hash = H(prev_hash || canonical(row))`), periodic
  anchoring/export.
- Velocity limits (MID/store/terminal/ref-prefix), anomaly scoring, **step-up** on high amounts,
  monitoring + alerting feeding the case workflow.

**Network / edge**
- **WAF**, rate limiting, IP allowlists for terminal/network ingress, DDoS protection, private networking
  for internal services, controlled egress for SPEI/webhooks.

**Supply chain & secrets**
- **Vault** (or cloud secrets manager); no secrets in code/env-in-repo; signed build artifacts; least-
  privilege CI credentials.

**Governance & compliance**
- **PLD/AML** (LFPIORPI): KYC/KYB, transaction monitoring, threshold detection, SAR/reporting workflow.
- **Data protection** (LFPDPPP/successor): minimization, consent, access logs, DSAR handling.
- **IFPE / Ley Fintech**: because prepaid balances are stored value — engage CNBV-track counsel early;
  the ledger's auditability and finality/void policy are inputs to authorization.
- **CONDUSEF**: user-protection, complaint handling, transparent T&Cs.
- Change control, SoD, audit trails → **ISO 27001 / SOC 2** trajectory.

**Security testing gates**
- Threat modeling (**STRIDE**) per major feature; security review in CI; external **penetration test**
  before go-live; bug-bounty once public.

---

## 6. Development cycle (step by step)

**Per-feature loop** (your CLAUDE.md goal-driven execution, applied):
1. **Contract first** — edit `openapi.yaml` / `asyncapi.yaml`; the contract is the spec.
2. **Write the failing test** (PHPUnit, real Postgres) that encodes the success criterion.
3. **Implement** through the layers (Presentation → Application → Domain), delegating money to Core.
4. **Gate locally** — Pint (format), PHPStan/Larastan (static), Deptrac (seam), the test suite.
5. **Review** — diff traces to the request only (surgical-changes rule); no speculative code.
6. **Merge** behind a feature flag; deploy to staging; smoke; promote.

**CI/CD pipeline (GitHub Actions) — gates in order, any red fails the build:**
```
lint/format (Pint) → static analysis (Larastan) → Deptrac (seam) →
PHPUnit (Postgres service) → security scans (composer audit + SAST + secret scan) →
build image + SBOM → deploy STAGING → smoke/contract tests → manual approve → deploy PROD (blue-green)
```

**Environments:** local (Docker Compose: app + Postgres + Redis + MinIO) → CI → **staging** (prod-like,
mock networks) → **production**. Config via env only (12-Factor); secrets from Vault.

**Branching & release:** trunk-based (short-lived branches) or GitFlow-lite; protected `main`; mandatory
review; Conventional Commits; SemVer tags; changelog; blue-green or canary rollout with fast rollback.

**Quality bars:** test pyramid (many unit on Domain, focused feature tests on use-cases, few end-to-end);
coverage floors on `Core/` and `Domain/`; mutation testing on the ledger if you want the highest assurance.

---

## 7. Phased roadmap

Sequenced by dependency, not calendar. Each phase has an **exit criterion** — you don't start the next
until it's green.

**Phase 0 — Foundations & contracts**
Repo, `Gateway` module scaffold, Docker dev env, CI/CD skeleton with all gates wired, Deptrac ruleset,
`openapi.yaml` + `asyncapi.yaml` authored from the docs, secrets baseline (Vault + KMS), observability
skeleton.
*Exit:* CI green on an empty module; contracts lint; Deptrac enforces the seam.

**Phase 1 — Money spine**
Issuers/merchants (MID registry + keys), `pos_wallets`, top-ups, the double-entry ledger, the
overdraft-guarded posting, `/gateway:add-ledger-posting confirmed_collection` + `topup_applied`.
Tamper-evident audit log live.
*Exit:* all ledger invariant tests pass; daily-close job asserts balance; no path can overdraw a wallet.

**Phase 2 — Instruments**
`Core/References`: deterministic + stored mapping, human ref (MOD 97-10), Code128 payload, signed QR
(HMAC v1, versioned `kid`), verification path, key rotation.
*Exit:* generate → verify round-trips; tampered/expired/replayed instruments rejected.

**Phase 3 — Issuer API + portal**
`/v1/payment-intents` (all modes + policies), status endpoints, signed replay-protected webhooks via the
outbox. Issuer self-service portal + developer portal (keys, sandbox, docs).
*Exit:* an issuer can create an intent, receive instruments, and get a signed webhook end-to-end.

**Phase 4 — POS API + mock adapters**
`cash/validate` (wallet check + reservation), `cash/confirm` (canonical posting + finality),
`cash/batch-confirm`. All three mock adapters via `/gateway:add-adapter`. Reference POS simulator.
*Exit:* a simulated collection draws the wallet, credits the issuer, and fires `payment.confirmed`;
batch ingestion works; duplicates blocked.

**Phase 5 — Settlement, reconciliation & ops console**
Issuer payouts (SPEI), network reconciliation + matching with tolerance, exceptions queue / case
workflow UI, daily-close integrity gate.
*Exit:* a full cycle (top-up → collections → settlement) reconciles; exceptions are worked to resolution.

**Phase 6 — Antifraud & hardening**
Velocity/anomaly scoring, step-up, mTLS/JWT for terminals, WAF + rate limits, ASVS L2/L3 pass, load +
chaos tests on the confirm hot path.
*Exit:* ASVS checklist met; external **pen test** passed; hot path meets latency/throughput SLOs.

**Phase 7 — Compliance & audit readiness**
KYC/KYB onboarding, AML monitoring + reporting workflow, data-protection controls, IFPE authorization
prep with counsel, SOC 2 / ISO 27001 groundwork.
*Exit:* compliance controls demonstrable; authorization path defined with counsel.

**Phase 8 — Productionization / SRE**
Observability + SLOs, DR + PITR drills, runbooks, on-call, blue-green deploys, go-live checklist.
*Exit:* staging survives failover drills; runbooks proven; go/no-go signed.

**Phase 9 — Real network fidelity**
Replace mock adapters with real network adapters (OXXO/Paynet/PayCash/Cashi-style, then ClubPago
layouts) — same contract, richer translators. Document each concession; don't bend the core.
*Exit:* at least one real network settles end-to-end in production.

**Phase 10 — Adapter extraction to NestJS (when batch/concurrency pain is real)**
Stand up the TS adapter service against the same contract; flip `AdapterGateway` to `RemoteHttpDriver`;
migrate networks one at a time. No core changes.
*Exit:* a network runs through the NestJS service in production with the PHP core untouched.

---

## 8. Where you'll need help

Solo-buildable with Claude Code through Phase 6. Bring in specialists for: an **external penetration
test** (Phase 6), **IFPE/Ley Fintech counsel** and an AML/compliance officer (Phase 7), and a
**SPEI-connected bank or regulated PSP** for real settlement rails (Phase 5 onward). Start those
conversations early — the banking and authorization timelines are the long poles, not the code.

---

### One-line summary
Build inner-to-outer (money spine → instruments → APIs → adapters → settlement), gate every phase on
invariants and named security standards, keep the seam clean so the adapter layer can go polyglot later,
and treat the banking/regulatory track as a parallel long-lead workstream from day one.
