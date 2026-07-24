<?php

namespace Corals\Modules\Gateway\Models;

use Corals\Foundation\Traits\HashTrait;
use Corals\Foundation\Traits\ModelActionsTrait;
use Corals\Foundation\Traits\ModelHelpersTrait;
use Corals\Foundation\Transformers\PresentableTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * GW-504: HashTrait/ModelHelpersTrait/ModelActionsTrait/PresentableTrait wire
 * this model into the admin edit-URL + action-button plumbing DataTables and
 * Transformers use (see Corals/core/Activity/Models/Activity.php for the same
 * pattern) — $config points at the resource_url/actions config those traits
 * read (config/gateway.php "models.reconciliation_exception").
 */
class ReconciliationException extends Model
{
    use HashTrait, ModelHelpersTrait, ModelActionsTrait, PresentableTrait;

    public $config = 'gateway.models.reconciliation_exception';

    protected $casts = [
        'refs' => 'array',
    ];

    protected $fillable = [
        'type',
        'refs',
        'state',
        'assignee',
        'resolution',
    ];
}
