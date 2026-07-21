# Architecture

## Layers

```
        ┌────────────────────────────────────────────────────────────┐
        │  ISSUER SIDE                                                 │
        │  POST /v1/payment-intents · GET status · webhooks OUT        │
        └───────────────┬────────────────────────────────────────────┘
                        │
   ┌────────────────────▼─────────────────────────────────────────────┐
   │  CORE  (owns all money + state — stays in Laravel forever)         │
   │  Intents · References/QR · Wallets · Ledger · Settlement · Issuers  │
   │  Idempotency · Replay cache · Key management · Audit                │
   └───────────▲──────────────────────────────────┬────────────────────┘
               │  AdapterGateway (driver-based)    │  Core Ingress Contract
               │  core → adapter callbacks          │  adapter → core commands
   ┌───────────┴──────────────────────────────────▼────────────────────┐
   │  ADAPTER LAYER  (pure translators — future NestJS service)          │
   │  Mock/RealtimeApi   Mock/Webhook   Mock/SftpBatch   (…real later)   │
   └───────────▲──────────────┬───────────────────┬─────────────────────┘
               │ REST API      │ webhook push       │ SFTP layout file
   ┌───────────┴──────────────┴───────────────────┴─────────────────────┐
   │  POS / RETAIL NETWORKS (heterogeneous, real-world)                  │
   └────────────────────────────────────────────────────────────────────┘
```

## The three network archetypes (why the adapter layer is the real core)

Mexican cash networks do NOT share one integration style. The adapter layer exists to collapse three
very different realities into one internal event model:

| Archetype        | Reality                                              | Adapter job                                   | validate-before-confirm? |
|------------------|------------------------------------------------------|-----------------------------------------------|--------------------------|
| Real-time API    | Network POS calls us to validate then confirm        | Map network payload ↔ `ValidateCollection`/`ConfirmCollection` | Yes |
| Webhook / near-RT | Network pushes confirms shortly after collection     | Verify signature, translate push → `ConfirmCollection` | Partial |
| SFTP batch       | Network drops a daily settlement file (fixed layout) | Poll, parse layout rows → `IngestSettlementBatch` → N confirms | **No** (offline at collection) |

The batch case is the *common* one in Mexico, not the exception. The core must treat a batch-ingested
confirm and a real-time confirm as the same normalized event, differing only in `finality_hint` and
whether a prior `validate` exists.

## Strangler-fig extraction plan

**Phase 1 (now):** Monolith. Adapters are PHP classes in `app/Adapters/*`. The `AdapterGateway` uses
`InProcessDriver` — but adapters still communicate ONLY via contract DTOs (`app/Contracts/Dto/*`), never
by importing core models. This forces the discipline early.

**Phase 2 (extraction):** Stand up the NestJS + TS adapter service. It implements the *same* Core
Ingress Contract (calls the core over HTTP/queue) and the *same* callback contract. Swap the core's
driver from `InProcessDriver` → `RemoteHttpDriver`. Move each network adapter from PHP to TS one at a
time (both can run in parallel behind the gateway during cutover). **No core code changes** — the core
still just emits/consumes contract DTOs.

Why it's safe: the only surface that changes is *transport*. DTOs, idempotency keys, replay cache,
ledger, and state machines all live in the core and are untouched. The contract is versioned, so a TS
adapter and a PHP adapter can speak the same `v1` simultaneously during migration.

## Mock adapters (v1 deliverable)

- **Mock/RealtimeApi** — an HTTP endpoint simulating a network that validates then confirms; supports
  fixed & variable amounts, partial payments, and injected failures (timeout, duplicate, tamper).
- **Mock/Webhook** — a scheduler that "collects" and pushes signed confirm webhooks after a delay.
- **Mock/SftpBatch** — writes sample layout files to a local dir on a cron; a poller parses them into
  batch confirms. This is the reference implementation for real batch networks later.

Each mock is a *teaching example* of the boundary: none of them import a core model; all speak DTOs.

## Where real fidelity plugs in later

Real OXXO/Paynet/PayCash/Cashi and ClubPago adapters are just richer versions of these mocks, shaped to
real reference-length limits, real layout formats, real commission and T+1–T+3 timing. The core, the
contract, and the wallet ledger do not change — that is the entire point of building clean-room first.
