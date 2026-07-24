<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\Models\WalletTopUp;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Corals\User\Models\User;
use Spatie\Permission\Models\Permission;

class OpsConsoleControllerTest extends GatewayTestCase
{
    private function actingAsOps(): User
    {
        $user = User::create([
            'name' => 'Ops User',
            'email' => 'ops-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
        ]);

        Permission::firstOrCreate([
            'name' => 'Gateway::ops-console.view',
            'guard_name' => config('auth.defaults.guard'),
        ]);

        $user->givePermissionTo('Gateway::ops-console.view');

        $this->actingAs($user);

        return $user;
    }

    public function test_a_guest_gets_no_access(): void
    {
        $this->get(route('gateway.ops.wallets'))->assertUnauthorized();
    }

    public function test_a_user_without_the_permission_is_forbidden(): void
    {
        $user = User::create([
            'name' => 'No Perm',
            'email' => 'no-perm-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $this->get(route('gateway.ops.wallets'))->assertForbidden();
    }

    public function test_wallets_page_lists_balances(): void
    {
        $this->actingAsOps();
        PosWallet::factory()->create(['network_id' => 'mock-realtime', 'balance_centavos' => 12345]);

        $this->get(route('gateway.ops.wallets'))->assertOk();
    }

    public function test_top_ups_page_renders(): void
    {
        $this->actingAsOps();
        $wallet = PosWallet::factory()->create();
        WalletTopUp::factory()->create(['pos_wallet_id' => $wallet->id]);

        $this->get(route('gateway.ops.top-ups'))->assertOk();
    }

    public function test_transactions_page_can_find_a_payment_by_network_txn_id(): void
    {
        $this->actingAsOps();
        Transaction::factory()->create(['network_txn_id' => 'NTX-FINDME']);

        $response = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('gateway.ops.transactions').'?draw=1&start=0&length=10&search[value]=NTX-FINDME');

        $response->assertOk();
        $response->assertJsonFragment(['network_txn_id' => 'NTX-FINDME']);
    }

    public function test_audit_log_page_renders(): void
    {
        $this->actingAsOps();

        $this->get(route('gateway.ops.audit-log'))->assertOk();
    }
}
