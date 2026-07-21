# Getting Started — Phase 0 bootstrap

From an empty directory to a first green vertical slice. Each step lists the **terminal commands** and
the **Claude Code prompt** to type. Don't advance past a step until its *verify* line is green.

The whole flow leans on what's already in the repo: the root `CLAUDE.md` (Laravel 13 conventions), the
`Gateway` module `CLAUDE.md` (invariants + seam), the `docs/` (the design), and the `/gateway:*` slash
commands. Claude Code re-reads those every session, so your prompts can stay short.

---

## Prerequisites

Verify before starting: **PHP 8.3**, **Composer 2**, **Node 20+**, **Docker + Compose**, **Claude Code**,
and your **Laraship license / access** (the private `packages.laraship.com` repo you already use).

```bash
php -v && composer --version && node -v && docker -v && claude --version
```

---

## Step 1 — Base platform in the empty directory

Provision Laraship the way you already do (your licensed `create-project`, or a `git clone` of your
baseline) into the empty dir, then install and run the wizard. Point the default connection at Postgres.

```bash
# (obtain the Laraship base via your standard licensed process, then:)
composer install
cp .env.example .env
php artisan key:generate
```

Bring up the dev services (file dropped in Step 3, but you need Postgres/Redis now):

```bash
docker compose up -d postgres redis minio
```

Edit `.env` deltas (this is the only DB posture that matters here):

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gateway
DB_USERNAME=gateway
DB_PASSWORD=secret
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

Run the Laraship installer (migrates + seeds the core modules):

```bash
php artisan corals:install
```

**Verify:** app boots, admin login works, `php artisan migrate:status` runs against Postgres.

---

## Step 2 — Drop the framework (the initial file distribution)

Extract `gateway-framework.zip` at the repo root. It merges without disturbing Laraship:

```bash
unzip gateway-framework.zip -d .
# then move the contents up one level if your unzip nested them under gateway-framework/
```

Layout after this step (● = ready to use, ○ = stub, generated later):

```
<repo-root>/
├── ● CLAUDE.md                       patched root (Laravel 13 / phpunit 12)
├── ● ROADMAP.md  ● GETTING_STARTED.md  ● SKELETON.md
├── ● docker-compose.yml  ● pint.json  ● phpstan.neon  ● depfile.yaml
├── ● .github/workflows/ci.yml
├── ● .claude/commands/gateway/{add-adapter,add-endpoint,add-ledger-posting}.md
└── Corals/modules/Gateway/
    ├── ● CLAUDE.md  ● module.json  ● docs/*  ● contracts/README.md
    ├── ○ Providers/ routes/ config/ database/ resources/ Http/ Models/
    │   ○ DataTables/ Policies/ Transformers/ tests/
    ├── ○ Core/{Intents,References,Wallets,Ledger/Postings,Settlement,Issuers,Merchants}/
    ├── ○ Contracts/{AdapterGateway.php, Dto/*, Drivers/*}
    ├── ○ Adapters/Mock/{RealtimeApi,Webhook,SftpBatch}/
    └── ○ contracts/{openapi,asyncapi}.yaml
```

```bash
git init && git add -A && git commit -m "chore: Laraship base + Gateway framework scaffold"
```

**Verify:** `git log` shows the baseline commit; the tree matches above.

---

## Step 3 — Quality tooling

Install the three gates and confirm they run.

```bash
composer require --dev larastan/larastan deptrac/deptrac laravel/pint
```

```bash
vendor/bin/pint --test Corals/modules/Gateway   # style
vendor/bin/phpstan analyse --no-progress        # static analysis (uses phpstan.neon)
vendor/bin/deptrac analyse                       # seam (uses depfile.yaml)
```

**Verify:** all three run without configuration errors (Deptrac passes trivially on stubs — that's fine).

---

## Step 4 — CI/CD skeleton

`.github/workflows/ci.yml` is already in place. Push and confirm the pipeline goes green on the empty
module. Gate order: **Pint → Larastan → Deptrac → migrate + PHPUnit(Postgres) → composer audit**.

```bash
git push -u origin main
```

**Verify:** the `gateway-ci` workflow is green.

---

## Step 5 — Register the Gateway module (resolve module.json against Laraship's real schema)

Generate a canonical module with Laraship's own scaffolder to get its exact manifest + provider + DB
registration, then reconcile our version onto it.

```bash
php artisan make:module Gateway PaymentIntent --no-interaction
```

Then, in **Claude Code**:

> Compare the `module.json` Laraship just generated at `Corals/modules/Gateway/module.json` with the one
> from the framework scaffold (in git history). Merge them: keep Laraship's exact schema/keys, but carry
> over our alias, description, dependencies (Foundation, User, Settings, Utility), and providers. Then
> reshape the generated module to the directory layout in `Corals/modules/Gateway/CLAUDE.md` — add the
> `Core/`, `Contracts/`, `Adapters/` namespaces without removing Laraship's standard folders. Don't write
> business logic yet. Summarize the diff.

Enable it:

```bash
php artisan corals:modules   # confirm Gateway is registered + enabled=1
php artisan route:list --path=v1   # loads clean (no routes yet)
```

**Verify:** module shows enabled; app still boots; `vendor/bin/deptrac analyse` still passes.

---

## Step 6 — Author the contracts (contract-first, Phase 0 deliverable)

In **Claude Code**:

> Author `Corals/modules/Gateway/contracts/openapi.yaml` and `asyncapi.yaml` from
> `Corals/modules/Gateway/docs/adapter-contract.md` and the API section of `docs/architecture.md`.
> OpenAPI: the Issuer API (`POST /v1/payment-intents`, status endpoints) and POS API
> (`/v1/cash/validate`, `/v1/cash/confirm`, `/v1/cash/batch-confirm`), plus the issuer webhook payloads.
> AsyncAPI: the adapter⇄core messages `ValidateCollection`, `ConfirmCollection`, `IngestSettlementBatch`
> and their results. Money fields are integer centavos. Add examples. Keep both at `contract_v: 1`.

Optionally lint them (`spectral lint contracts/openapi.yaml`).

**Verify:** both files validate; they match the DTOs in `docs/adapter-contract.md`.

> **Phase 0 exit gate:** CI green on the registered module · contracts authored and linting · Deptrac
> enforcing the seam · dev services up · secrets/KMS placeholders wired in `.env`. You are now ready to
> build features.

---

## Step 7 — Prove the loop: first Phase 1 slice (the money spine)

Do the smallest end-to-end slice that exercises the whole cycle — migrations → posting → invariant tests
— so the development loop is proven before you scale it.

In **Claude Code**:

> Read `Corals/modules/Gateway/docs/data-model.md`. Create the Phase 1 migrations for `merchants`,
> `pos_wallets` (with `balance_centavos BIGINT`, `CHECK (balance_centavos >= 0)`), `wallet_top_ups`, and
> `ledger_entries`. Use `php artisan make:migration`. Follow the root CLAUDE.md DB rules. Then create
> Eloquent models in `Corals/modules/Gateway/Models/`. Don't add business logic beyond the schema.

Then run the ledger command:

```
/gateway:add-ledger-posting topup_applied
/gateway:add-ledger-posting confirmed_collection
```

```bash
php artisan migrate
php artisan test --compact --filter=Ledger
```

**Verify (the loop works):** the balanced-posting, wallet-non-negative, idempotency, and drift tests all
pass; a debit exceeding balance is rejected with nothing written; CI stays green.

---

## How to drive Claude Code from here

- **Contract first, always.** Before any endpoint, edit `contracts/` — then implement to match.
- Use the slash commands as the unit of work: `/gateway:add-endpoint`, `/gateway:add-adapter`,
  `/gateway:add-ledger-posting`. Each re-reads the invariants so they can't drift.
- One vertical slice at a time, inner-to-outer, following `ROADMAP.md` phases; never advance past a
  phase's exit gate.
- Let CI be the judge: Pint, Larastan, Deptrac, and PHPUnit are the definition of done. If Deptrac ever
  goes red, an adapter reached into `Core`/`Models` — stop and fix the seam, don't suppress the rule.
- Keep diffs surgical (root CLAUDE.md rule): every changed line traces to the task.

Next after the spine: Phase 2 instruments (references/QR), then Phase 3 Issuer API + portal. The banking
(SPEI partner) and IFPE authorization tracks start in parallel now — they're the long poles.
