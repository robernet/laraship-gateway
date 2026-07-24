<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Modules\Gateway\database\factories\NetworkAdapterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry row for a network's adapter config (docs/data-model.md
 * "Infrastructure tables"): which archetype (realtime/webhook/sftp) a
 * network_id speaks and which contract version it declares
 * (docs/adapter-contract.md "Versioning"). Admin/internal-facing only — no
 * public_id, unlike POS/Issuer-facing lookup models (NetworkCredential,
 * PosWallet, Issuer). Not queried by Core\Collections\* today; those resolve
 * by network_id string directly.
 */
class NetworkAdapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'network_id',
        'archetype',
        'config',
        'contract_version',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
        ];
    }

    protected static function newFactory(): NetworkAdapterFactory
    {
        return NetworkAdapterFactory::new();
    }
}
