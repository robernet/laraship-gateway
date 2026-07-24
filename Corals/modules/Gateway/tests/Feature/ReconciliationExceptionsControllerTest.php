<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Ledger\Postings\ConfirmedCollectionPosting;
use Corals\Modules\Gateway\Models\Issuer;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Corals\User\Models\User;

class ReconciliationExceptionsControllerTest extends GatewayTestCase
{
    private function actingAsAdmin(): User
    {
        $user = User::create([
            'name' => 'Ops Admin',
            'email' => 'ops-admin-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
        ]);

        \Settings::set('super_user_id', $user->id);

        $this->actingAs($user);

        return $user;
    }

    public function test_a_guest_gets_no_access(): void
    {
        $exception = ReconciliationException::create(['type' => 'unmatched_confirm', 'refs' => []]);

        // This app registers no global unauthenticated-redirect for admin
        // routes (only portal/* gets one) — a guest here gets a bare 401.
        $this->get(route('gateway.reconciliation-exceptions.edit', $exception->hashed_id))
            ->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_an_admin_can_list_and_view_a_case(): void
    {
        $this->actingAsAdmin();

        $exception = ReconciliationException::create(['type' => 'unmatched_confirm', 'refs' => []]);

        $this->get(route('gateway.reconciliation-exceptions.index'))->assertOk();
        $this->get(route('gateway.reconciliation-exceptions.edit', $exception->hashed_id))->assertOk();
    }

    public function test_a_case_can_be_worked_end_to_end_through_the_http_endpoints(): void
    {
        $this->actingAsAdmin();

        $issuer = Issuer::factory()->create();
        $wallet = PosWallet::factory()->create(['balance_centavos' => 10000]);

        $postingId = (new ConfirmedCollectionPosting())->apply(
            transactionId: 1,
            posWalletId: $wallet->id,
            issuerId: $issuer->id,
            amountCentavos: 10000,
            commissionCentavos: 150,
            feeCentavos: 50
        );

        $exception = ReconciliationException::create([
            'type' => 'duplicate',
            'refs' => ['network_txn_id' => 'NTX-DUP'],
        ]);

        $editUrl = route('gateway.reconciliation-exceptions.edit', $exception->hashed_id);
        $updateUrl = route('gateway.reconciliation-exceptions.update', $exception->hashed_id);

        $this->put($updateUrl, ['workflow_action' => 'assign', 'assignee' => 'ops-alice'])
            ->assertRedirect($editUrl);
        $this->assertSame('ops-alice', $exception->fresh()->assignee);

        $this->put($updateUrl, ['workflow_action' => 'investigate'])
            ->assertRedirect($editUrl);
        $this->assertSame('investigating', $exception->fresh()->state);

        $this->put($updateUrl, [
            'workflow_action' => 'resolve',
            'resolution' => 'Duplicate remittance row, reversing the erroneous posting.',
            'reverse_posting_id' => $postingId,
        ])->assertRedirect($editUrl);

        $exception->refresh();
        $this->assertSame('resolved', $exception->state);
        $this->assertNotNull($exception->refs['corrective_posting_id']);
        $this->assertSame(10000, $wallet->fresh()->balance_centavos);
    }
}
