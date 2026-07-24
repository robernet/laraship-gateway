<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Models\NetworkCredential;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class IssueNetworkTokenTest extends GatewayTestCase
{
    public function test_issuing_a_token_creates_the_credential_on_first_use(): void
    {
        $this->artisan('gateway:network:issue-token', ['network_id' => 'mock-realtime'])->assertSuccessful();

        $credential = NetworkCredential::where('network_id', 'mock-realtime')->sole();
        $this->assertSame(NetworkAbility::values(), $credential->tokens()->sole()->abilities);
    }

    public function test_reissuing_reuses_the_existing_credential(): void
    {
        $this->artisan('gateway:network:issue-token', ['network_id' => 'mock-realtime'])->assertSuccessful();
        $this->artisan('gateway:network:issue-token', ['network_id' => 'mock-realtime'])->assertSuccessful();

        $this->assertSame(1, NetworkCredential::where('network_id', 'mock-realtime')->count());
    }

    public function test_ttl_option_overrides_the_configured_default(): void
    {
        $this->artisan('gateway:network:issue-token', [
            'network_id' => 'mock-realtime',
            '--ttl' => 15,
        ])->assertSuccessful();

        $token = NetworkCredential::where('network_id', 'mock-realtime')->sole()->tokens()->sole();

        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addMinutes(15)->addSecond()));
    }
}
