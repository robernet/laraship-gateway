<?php

namespace Corals\Modules\Gateway\DataTables;

use Corals\Foundation\DataTables\BaseDataTable;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Transformers\PosWalletTransformer;
use Yajra\DataTables\EloquentDataTable;

/**
 * GW-506: ops console — wallet balances (docs/data-model.md "pos_wallets").
 * Read-only: no create/edit/delete, ops browses balances here to answer
 * "where is this payment?" without SQL.
 */
class PosWalletsDataTable extends BaseDataTable
{
    public function dataTable($query)
    {
        $this->setResourceUrl('ops/wallets');

        $dataTable = new EloquentDataTable($query);

        return $dataTable->setTransformer(new PosWalletTransformer());
    }

    /**
     * @param PosWallet $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(PosWallet $model)
    {
        return $model->newQuery();
    }

    protected function getColumns()
    {
        return [
            'id' => ['visible' => false],
            'public_id' => ['title' => 'Public ID'],
            'network_id' => ['title' => 'Network'],
            'external_store_id' => ['title' => 'Store'],
            'balance_centavos' => ['title' => 'Balance (centavos)'],
            'reserved_centavos' => ['title' => 'Reserved (centavos)'],
            'currency' => ['title' => 'Currency'],
            'status' => ['title' => 'Status'],
            'created_at' => ['title' => trans('Corals::attributes.created_at')],
        ];
    }

    public function getFilters()
    {
        return [
            'network_id' => ['title' => 'Network', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
            'status' => ['title' => 'Status', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
        ];
    }

    protected function getOptions()
    {
        return ['resource_url' => url('ops/wallets')];
    }
}
