<?php

namespace Corals\Modules\Gateway\DataTables;

use Corals\Foundation\DataTables\BaseDataTable;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Corals\Modules\Gateway\Transformers\WalletTopUpTransformer;
use Yajra\DataTables\EloquentDataTable;

/**
 * GW-506: ops console — top-up history (docs/settlement-reconciliation.md
 * "Top-up ingestion & matching"). Read-only.
 */
class WalletTopUpsDataTable extends BaseDataTable
{
    public function dataTable($query)
    {
        $this->setResourceUrl('ops/top-ups');

        $dataTable = new EloquentDataTable($query);

        return $dataTable->setTransformer(new WalletTopUpTransformer());
    }

    /**
     * @param WalletTopUp $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(WalletTopUp $model)
    {
        return $model->with('posWallet')->newQuery();
    }

    protected function getColumns()
    {
        return [
            'id' => ['visible' => false],
            'pos_wallet_id' => ['visible' => false],
            'wallet_public_id' => ['title' => 'Wallet', 'orderable' => false, 'searchable' => false],
            'amount_centavos' => ['title' => 'Amount (centavos)'],
            'spei_ref' => ['title' => 'SPEI ref'],
            'clabe_origin' => ['title' => 'CLABE origin'],
            'status' => ['title' => 'Status'],
            'applied_at' => ['title' => 'Applied at'],
            'created_at' => ['title' => trans('Corals::attributes.created_at')],
        ];
    }

    public function getFilters()
    {
        return [
            'status' => ['title' => 'Status', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
            'spei_ref' => ['title' => 'SPEI ref', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
        ];
    }

    protected function getOptions()
    {
        return ['resource_url' => url('ops/top-ups')];
    }
}
