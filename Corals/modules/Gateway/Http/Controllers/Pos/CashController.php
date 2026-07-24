<?php

namespace Corals\Modules\Gateway\Http\Controllers\Pos;

use Corals\Foundation\Http\Controllers\APIBaseController;
use Corals\Modules\Gateway\Core\Collections\ValidateCollection;
use Corals\Modules\Gateway\Http\Requests\ValidateCollectionRequest;

/**
 * POST /v1/cash/{validate,confirm,batch-confirm}. See
 * Corals/modules/Gateway/CLAUDE.md for invariants — confirm/batch-confirm
 * land in GW-402/GW-403.
 */
class CashController extends APIBaseController
{
    public function validateCollection(ValidateCollectionRequest $request)
    {
        $result = (new ValidateCollection())->handle($request->validated());

        return response()->json($result);
    }
}
