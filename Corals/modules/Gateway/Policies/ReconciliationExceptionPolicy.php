<?php

namespace Corals\Modules\Gateway\Policies;

use Corals\Foundation\Policies\BasePolicy;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\User\Models\User;

class ReconciliationExceptionPolicy extends BasePolicy
{
    protected $administrationPermission = 'Administrations::admin.gateway';

    public function view(User $user)
    {
        return $user->can('Gateway::reconciliation-exception.view');
    }

    public function update(User $user, ReconciliationException $exception)
    {
        return $user->can('Gateway::reconciliation-exception.manage');
    }
}
