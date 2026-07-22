<?php

namespace Corals\Modules\Gateway\Providers;

use Corals\Foundation\Providers\BaseInstallModuleServiceProvider;
use Corals\Modules\Gateway\database\migrations\IssuersMerchantsTables;
use Corals\Modules\Gateway\database\migrations\LedgerEntriesTables;
use Corals\Modules\Gateway\database\migrations\PosWalletsTables;

class InstallModuleServiceProvider extends BaseInstallModuleServiceProvider
{
    protected $migrations = [
        IssuersMerchantsTables::class,
        PosWalletsTables::class,
        LedgerEntriesTables::class,
    ];
}
