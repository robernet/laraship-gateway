<?php

namespace Corals\Modules\Gateway\Http\Controllers;

use Corals\Foundation\Http\Controllers\BaseController;
use Corals\Modules\Gateway\DataTables\AuditLogDataTable;
use Corals\Modules\Gateway\DataTables\PosWalletsDataTable;
use Corals\Modules\Gateway\DataTables\TransactionsDataTable;
use Corals\Modules\Gateway\DataTables\WalletTopUpsDataTable;

/**
 * GW-506: read-only ops console — wallet balances, top-up history,
 * transaction search, audit browser. One shared permission
 * ("Gateway::ops-console.view") gates all four; there is nothing to create,
 * edit, or delete here, so a per-model Policy class would be pure
 * boilerplate.
 */
class OpsConsoleController extends BaseController
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            abort_unless(user()->can('Gateway::ops-console.view'), 403);

            return $next($request);
        });
    }

    public function wallets(PosWalletsDataTable $dataTable)
    {
        return $dataTable->render('Gateway::ops_console.index', [
            'title' => 'Wallets', 'hideCreate' => true,
        ]);
    }

    public function topUps(WalletTopUpsDataTable $dataTable)
    {
        return $dataTable->render('Gateway::ops_console.index', [
            'title' => 'Top-ups', 'hideCreate' => true,
        ]);
    }

    public function transactions(TransactionsDataTable $dataTable)
    {
        return $dataTable->render('Gateway::ops_console.index', [
            'title' => 'Transactions', 'hideCreate' => true,
        ]);
    }

    public function auditLog(AuditLogDataTable $dataTable)
    {
        return $dataTable->render('Gateway::ops_console.index', [
            'title' => 'Audit log', 'hideCreate' => true,
        ]);
    }
}
