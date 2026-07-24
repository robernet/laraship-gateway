<?php

namespace Corals\Modules\Gateway\Http\Controllers;

use Corals\Foundation\Http\Controllers\BaseController;
use Corals\Modules\Gateway\Core\Exceptions\ReconciliationExceptionWorkflow;
use Corals\Modules\Gateway\DataTables\ReconciliationExceptionsDataTable;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * GW-504: admin case workflow for reconciliation_exceptions — list, then
 * assign/investigate/resolve a single case from its edit page. See
 * Core\Exceptions\ReconciliationExceptionWorkflow for the actual state
 * transitions and the corrective-posting rules.
 */
class ReconciliationExceptionsController extends BaseController
{
    public function __construct()
    {
        $this->resource_url = config('gateway.models.reconciliation_exception.resource_url');
        $this->resource_model = new ReconciliationException();
        $this->title = 'Gateway::module.reconciliation_exception.title';
        $this->title_singular = 'Gateway::module.reconciliation_exception.title_singular';

        parent::__construct();
    }

    public function index(Request $request, ReconciliationExceptionsDataTable $dataTable)
    {
        $this->authorize('view', ReconciliationException::class);

        return $dataTable->render('Gateway::reconciliation_exceptions.index', ['hideCreate' => true]);
    }

    public function edit(ReconciliationException $reconciliationException)
    {
        $this->authorize('update', $reconciliationException);

        return view('Gateway::reconciliation_exceptions.edit', [
            'exception' => $reconciliationException,
        ]);
    }

    public function update(Request $request, ReconciliationException $reconciliationException)
    {
        $this->authorize('update', $reconciliationException);

        $workflow = new ReconciliationExceptionWorkflow();

        try {
            switch ($request->input('workflow_action')) {
                case 'assign':
                    $request->validate(['assignee' => 'required|string|max:255']);
                    $workflow->assign($reconciliationException->id, $request->input('assignee'));
                    $message = trans('Gateway::messages.reconciliation_exception.assigned');
                    break;

                case 'investigate':
                    $workflow->investigate($reconciliationException->id);
                    $message = trans('Gateway::messages.reconciliation_exception.investigating');
                    break;

                case 'resolve':
                    $request->validate(['resolution' => 'required|string']);
                    $workflow->resolve(
                        $reconciliationException->id,
                        $request->input('resolution'),
                        $request->filled('reverse_posting_id') ? $request->input('reverse_posting_id') : null
                    );
                    $message = trans('Gateway::messages.reconciliation_exception.resolved');
                    break;

                default:
                    abort(422, 'Unknown workflow action.');
            }
        } catch (RuntimeException $e) {
            return back()->with('flash', ['level' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()
            ->route('gateway.reconciliation-exceptions.edit', $reconciliationException->hashed_id)
            ->with('flash', ['level' => 'success', 'message' => $message]);
    }
}
