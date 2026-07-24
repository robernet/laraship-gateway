<?php

namespace Corals\Modules\Gateway\Transformers;

use Corals\Foundation\Transformers\BaseTransformer;
use Corals\Modules\Gateway\Models\ReconciliationException;

class ReconciliationExceptionTransformer extends BaseTransformer
{
    public function __construct($extras = [])
    {
        $this->resource_url = config('gateway.models.reconciliation_exception.resource_url');

        parent::__construct($extras);
    }

    /**
     * @param ReconciliationException $exception
     * @return array
     * @throws \Throwable
     */
    public function transform(ReconciliationException $exception)
    {
        $transformedArray = [
            'id' => $exception->id,
            'checkbox' => $this->generateCheckboxElement($exception),
            'type' => $exception->type,
            'refs' => json_encode($exception->refs),
            'state' => $exception->state,
            'assignee' => $exception->assignee ?? '-',
            'created_at' => format_date_time($exception->created_at),
            'action' => $this->actions($exception),
        ];

        return parent::transformResponse($transformedArray);
    }
}
