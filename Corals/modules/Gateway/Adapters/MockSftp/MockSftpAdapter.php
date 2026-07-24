<?php

namespace Corals\Modules\Gateway\Adapters\MockSftp;

use Corals\Modules\Gateway\Contracts\AdapterGateway;
use Corals\Modules\Gateway\Contracts\Drivers\InProcessDriver;
use Corals\Modules\Gateway\Contracts\Dto\IngestSettlementBatch;
use Illuminate\Support\Facades\Storage;

/**
 * `sftp` archetype (docs/adapter-contract.md "IngestSettlementBatch"). Mock
 * network `mock-sftp` drops pipe-delimited settlement files on a disk;
 * poll() reads every file in the drop directory, parses its layout into
 * IngestSettlementBatch rows and forwards the whole batch through the
 * AdapterGateway in one call — per the strangler seam
 * (Corals/modules/Gateway/CLAUDE.md): imports ONLY Contracts\*, never
 * Core/Models/Http.
 *
 * Layout (one row per line, `|`-delimited, LF-terminated, matching
 * contracts/asyncapi.yaml IngestSettlementBatchRequest.rows):
 *   network_txn_id|mid|ref|amount_paid|collected_at
 *
 * amount_paid/collected_at are integer strings (centavos / unix timestamp).
 * A field that is missing or fails its own format check is OMITTED from the
 * parsed row rather than guessed at — Core's existing per-row handling
 * (Core\Collections\IngestSettlementBatch::handle()) already declines an
 * incomplete/invalid row into reconciliation_exceptions without aborting the
 * batch, so the adapter never needs Models access to flag it itself.
 */
final class MockSftpAdapter
{
    public function __construct(
        private readonly AdapterGateway $gateway = new InProcessDriver(),
    ) {
    }

    public function poll(string $disk, string $directory, string $networkId): array
    {
        $results = [];

        foreach (Storage::disk($disk)->files($directory) as $path) {
            $results[] = $this->ingest($networkId, pathinfo($path, PATHINFO_FILENAME), Storage::disk($disk)->get($path));
            Storage::disk($disk)->delete($path);
        }

        return $results;
    }

    public function ingest(string $networkId, string $batchId, string $fileContents): array
    {
        $lines = array_filter(preg_split('/\R/', trim($fileContents)), fn (string $line) => $line !== '');

        return $this->gateway->ingestSettlementBatch(IngestSettlementBatch::fromArray([
            'contract_v' => 1,
            'network_id' => $networkId,
            'batch_id' => $batchId,
            'rows' => array_map($this->parseLine(...), array_values($lines)),
        ]))->toArray();
    }

    private function parseLine(string $line): array
    {
        $fields = explode('|', $line);
        $row = [];

        foreach (['network_txn_id', 'mid', 'ref'] as $i => $key) {
            if (($fields[$i] ?? '') !== '') {
                $row[$key] = $fields[$i];
            }
        }

        foreach ([3 => 'amount_paid', 4 => 'collected_at'] as $i => $key) {
            if (isset($fields[$i]) && ctype_digit($fields[$i])) {
                $row[$key] = (int) $fields[$i];
            }
        }

        return $row;
    }
}
