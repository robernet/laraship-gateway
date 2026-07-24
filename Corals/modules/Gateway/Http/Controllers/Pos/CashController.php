<?php

namespace Corals\Modules\Gateway\Http\Controllers\Pos;

use Corals\Foundation\Http\Controllers\APIBaseController;
use Corals\Modules\Gateway\Core\Collections\ConfirmCollection;
use Corals\Modules\Gateway\Core\Collections\ValidateCollection;
use Corals\Modules\Gateway\Http\Requests\ConfirmCollectionRequest;
use Corals\Modules\Gateway\Http\Requests\ValidateCollectionRequest;

/**
 * POST /v1/cash/{validate,confirm,batch-confirm}. See
 * Corals/modules/Gateway/CLAUDE.md for invariants — batch-confirm lands in
 * GW-403.
 */
class CashController extends APIBaseController
{
    public function validateCollection(ValidateCollectionRequest $request)
    {
        $result = (new ValidateCollection())->handle($request->validated());

        return response()->json($result);
    }

    public function confirmCollection(ConfirmCollectionRequest $request)
    {
        $result = (new ConfirmCollection())->handle($request->validated());

        return response()->json($result);
    }
}
