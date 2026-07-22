<?php

namespace Corals\Modules\Gateway\Providers;

use Corals\Foundation\Providers\BaseInstallModuleServiceProvider;
use Corals\Modules\Gateway\database\migrations\IssuersMerchantsTables;

class InstallModuleServiceProvider extends BaseInstallModuleServiceProvider
{
    protected $migrations = [
        IssuersMerchantsTables::class,
    ];
}
