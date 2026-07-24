<?php

namespace Corals\Modules\Gateway\DataTables;

use Corals\Foundation\DataTables\BaseDataTable;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\Transformers\TransactionTransformer;
use Yajra\DataTables\EloquentDataTable;

/**
 * GW-506: ops console — transaction search (docs/data-model.md
 * "transactions"). The literal "where is this payment?" view: searchable by
 * network_txn_id/public_id via the DataTable's built-in global search.
 * Read-only.
 */
class TransactionsDataTable extends BaseDataTable
{
    public function dataTable($query)
    {
        $this->setResourceUrl('ops/transactions');

        $dataTable = new EloquentDataTable($query);

        return $dataTable->setTransformer(new TransactionTransformer());
    }

    /**
     * @param Transaction $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Transaction $model)
    {
        return $model->newQuery();
    }

    protected function getColumns()
    {
        return [
            'id' => ['visible' => false],
            'public_id' => ['title' => 'Public ID'],
            'network_id' => ['title' => 'Network'],
            'network_txn_id' => ['title' => 'Network txn ID'],
            'pos_wallet_id' => ['title' => 'Wallet ID'],
            'amount_centavos' => ['title' => 'Amount (centavos)'],
            'state' => ['title' => 'State'],
            'is_partial' => ['title' => 'Partial'],
            'collected_at' => ['title' => 'Collected at'],
            'confirmed_at' => ['title' => 'Confirmed at'],
        ];
    }

    public function getFilters()
    {
        return [
            'network_id' => ['title' => 'Network', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
            'network_txn_id' => ['title' => 'Network txn ID', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
            'state' => ['title' => 'State', 'class' => 'col-md-3', 'type' => 'text', 'active' => true],
        ];
    }

    protected function getOptions()
    {
        return ['resource_url' => url('ops/transactions')];
    }
}
