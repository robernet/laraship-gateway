<?php

namespace Corals\Modules\Gateway\DataTables;

use Corals\Foundation\DataTables\BaseDataTable;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Transformers\ReconciliationExceptionTransformer;
use Yajra\DataTables\EloquentDataTable;

/**
 * GW-504: admin exceptions queue (docs/settlement-reconciliation.md
 * "Exceptions queue"). See Corals/modules/Gateway/CLAUDE.md for invariants.
 */
class ReconciliationExceptionsDataTable extends BaseDataTable
{
    public function dataTable($query)
    {
        $this->setResourceUrl(config('gateway.models.reconciliation_exception.resource_url'));

        $dataTable = new EloquentDataTable($query);

        return $dataTable->setTransformer(new ReconciliationExceptionTransformer());
    }

    /**
     * @param ReconciliationException $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(ReconciliationException $model)
    {
        return $model->newQuery();
    }

    protected function getColumns()
    {
        return [
            'id' => ['visible' => false],
            'type' => ['title' => trans('Gateway::attributes.reconciliation_exception.type')],
            'refs' => ['title' => trans('Gateway::attributes.reconciliation_exception.refs')],
            'state' => ['title' => trans('Gateway::attributes.reconciliation_exception.state')],
            'assignee' => ['title' => trans('Gateway::attributes.reconciliation_exception.assignee')],
            'created_at' => ['title' => trans('Corals::attributes.created_at')],
        ];
    }

    public function getFilters()
    {
        return [
            'type' => [
                'title' => trans('Gateway::attributes.reconciliation_exception.type'),
                'class' => 'col-md-3',
                'type' => 'select2',
                'options' => [
                    'unmatched_confirm' => 'unmatched_confirm',
                    'amount_mismatch' => 'amount_mismatch',
                    'duplicate' => 'duplicate',
                    'orphan_topup' => 'orphan_topup',
                    'negative_drift' => 'negative_drift',
                ],
                'active' => true,
            ],
            'state' => [
                'title' => trans('Gateway::attributes.reconciliation_exception.state'),
                'class' => 'col-md-3',
                'type' => 'select2',
                'options' => [
                    'open' => 'open',
                    'investigating' => 'investigating',
                    'resolved' => 'resolved',
                ],
                'active' => true,
            ],
        ];
    }

    protected function getOptions()
    {
        $url = url(config('gateway.models.reconciliation_exception.resource_url'));

        return ['resource_url' => $url];
    }
}
