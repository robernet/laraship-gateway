# Gateway framework — skeleton

Extract into your Laraship repo root; it merges cleanly (adds a module, a command
namespace, and replaces the root CLAUDE.md).

REAL (authored, ready to use):
- CLAUDE.md                                  patched root (Laravel 13 / phpunit 12)
- .claude/commands/gateway/*.md              /gateway:add-adapter | add-endpoint | add-ledger-posting
- Corals/modules/Gateway/CLAUDE.md           module anchor (invariants + Laraship map)
- Corals/modules/Gateway/module.json         manifest (verify vs a sibling module)
- Corals/modules/Gateway/docs/*.md           full design (architecture, ledger, seam, etc.)
- Corals/modules/Gateway/contracts/README.md contract source-of-truth note

STUB (empty placeholders — generated in M0-M4, shown so the layout is visible):
- Providers/ routes/ config/ database/ resources/ Http/ Models/ DataTables/
  Policies/ Transformers/ tests/         standard Laraship module surface
- Core/                                  owns all money + state (services)
- Contracts/ (Dto, AdapterGateway, Drivers)   THE SEAM (NestJS migration boundary)
- Adapters/Mock/{RealtimeApi,Webhook,SftpBatch}/   pure translators
- contracts/{openapi,asyncapi}.yaml      authored first in M0
- depfile.yaml                           Deptrac rules: Adapters -> Contracts only

Start: php artisan make:module Gateway PaymentIntent --no-interaction, reshape to
Corals/modules/Gateway/CLAUDE.md, then author the two contract files.
