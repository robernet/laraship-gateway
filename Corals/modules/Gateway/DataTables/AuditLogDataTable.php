<?php

namespace Corals\Modules\Gateway\DataTables;

use Corals\Foundation\DataTables\BaseDataTable;
use Corals\Modules\Gateway\Models\AuditLog;
use Corals\Modules\Gateway\Transformers\AuditLogTransformer;
use Yajra\DataTables\EloquentDataTable;

/**
 * GW-506: ops console — audit browser (docs/security-antifraud.md
 * "Tamper-evident audit log"). Read-only, append-only source table.
 */
class AuditLogDataTable extends BaseDataTable
{
    public function dataTable($query)
    {
        $this->setResourceUrl('ops/audit-log');

        $dataTable = new EloquentDataTable($query);

        return $dataTable->setTransformer(new AuditLogTransformer());
    }

    /**
     * @param AuditLog $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(AuditLog $model)
    {
        return $model->newQuery();
    }

    protected function getColumns()
    {
        return [
            'id' => ['visible' => false],
            'actor' => ['title' => 'Actor'],
            'action' => ['title' => 'Action'],
            'subject_type' => ['title' => 'Subject type'],
            'subject_id' => ['title' => 'Subject ID'],
            'payload' => ['title' => 'Payload'],
            'created_at' => ['title' => trans('Corals::attributes.created_at')],
        ];
    }

    public function getFilters()
    {
        return [
            'actor' => ['title' => 'Actor', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
            'action' => ['title' => 'Action', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
        ];
    }

    protected function getOptions()
    {
        return ['resource_url' => url('ops/audit-log')];
    }
}
