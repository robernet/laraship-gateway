# Contracts

The source of truth for every boundary. Edit these BEFORE implementing either side.

- **`openapi.yaml`** — synchronous REST: Issuer API (`/v1/payment-intents`, status) and POS API
  (`/v1/cash/validate`, `/v1/cash/confirm`, `/v1/cash/batch-confirm`), plus issuer webhook payloads.
- **`asyncapi.yaml`** — the adapter⇄core event contract: `ValidateCollection`, `ConfirmCollection`,
  `IngestSettlementBatch` and their results/callbacks. This is the schema the future **NestJS + TS**
  adapter service must speak. Keep it language-neutral; money is always integer centavos.

Both files carry a `contract_v`. Breaking changes bump the major version and run side-by-side until all
adapters (PHP and TS) upgrade — this is what makes the strangler migration non-disruptive.

Generate initial skeletons with:
```
/add-endpoint POST /v1/payment-intents
/add-endpoint POST /v1/cash/validate
/add-endpoint POST /v1/cash/confirm
```
and let each command author the matching operation in `openapi.yaml`. For the async contract, model the
three commands in `docs/adapter-contract.md` as AsyncAPI messages with the JSON examples given there.
