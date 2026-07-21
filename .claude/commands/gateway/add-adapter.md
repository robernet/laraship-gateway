---
description: Scaffold a Gateway network adapter that respects the strangler seam (Laraship module)
argument-hint: <network_id> <archetype: realtime|webhook|sftp>
---

Read `Corals/modules/Gateway/CLAUDE.md` and `Corals/modules/Gateway/docs/adapter-contract.md` first.
The seam invariants are non-negotiable. Use `search-docs` before any Laravel/package change (root rule).

Network: `$1`  ·  Archetype: `$2`

Do this:

1. Create `Corals/modules/Gateway/Adapters/{StudlyNetworkId}/` implementing the archetype:
   - `realtime` → an entrypoint mapping native payload ↔ `ValidateCollection` / `ConfirmCollection`.
   - `webhook`  → a signed-webhook receiver that verifies signature, then emits `ConfirmCollection`.
   - `sftp`     → a poller that parses the layout file into `IngestSettlementBatch`.
2. The adapter imports ONLY `Corals\Modules\Gateway\Contracts\*`. It MUST NOT import `Core\*`,
   `Models\*`, any Eloquent model, or a controller. All core I/O is via contract DTOs through the
   `AdapterGateway`.
3. Money to/from **integer centavos**. Reject/flag anything unmappable — never guess.
4. Register the adapter in the `network_adapters` table (id=`$1`, archetype=`$2`, contract_version=current).
5. **Deptrac:** ensure `depfile.yaml` defines layers `Adapters, Contracts, Core, Http, Models` and the
   ruleset allows `Adapters → Contracts` only; add the layer mapping for this adapter and confirm
   `vendor/bin/deptrac analyse` passes (Adapters → Core/Models/Http must fail if introduced).
6. **PHPUnit** (`php artisan make:test --phpunit {StudlyNetworkId}AdapterTest`): a contract-conformance
   test feeding sample native input and asserting the emitted DTO validates against
   `contracts/asyncapi.yaml` and round-trips.
7. For `sftp`, include a sample layout fixture + a parser test with ≥1 malformed row that must route to
   `reconciliation_exceptions` (not crash the batch).

Summarize files created and any layout/format assumptions made.
