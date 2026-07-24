<?php

namespace Tests\Feature;

use Corals\Modules\Gateway\Core\Networks\NetworkAbility;
use Corals\Modules\Gateway\Models\NetworkCredential;
use Corals\Modules\Gateway\tests\GatewayTestCase;

class IssueTerminalTokenTest extends GatewayTestCase
{
    public function test_issuing_a_token_creates_the_terminal_credential_on_first_use(): void
    {
        $this->artisan('gateway:terminal:issue-token', [
            'network_id' => 'mock-realtime',
            'terminal_id' => 'T-01',
        ])->assertSuccessful();

        $credential = NetworkCredential::where('network_id', 'mock-realtime')->where('terminal_id', 'T-01')->sole();
        $this->assertSame(NetworkAbility::values(), $credential->tokens()->sole()->abilities);
    }

    public function test_reissuing_reuses_the_existing_terminal_credential(): void
    {
        $this->artisan('gateway:terminal:issue-token', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-01'])->assertSuccessful();
        $this->artisan('gateway:terminal:issue-token', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-01'])->assertSuccessful();

        $this->assertSame(1, NetworkCredential::where('network_id', 'mock-realtime')->where('terminal_id', 'T-01')->count());
    }

    public function test_a_second_terminal_on_the_same_network_gets_its_own_credential(): void
    {
        $this->artisan('gateway:terminal:issue-token', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-01'])->assertSuccessful();
        $this->artisan('gateway:terminal:issue-token', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-02'])->assertSuccessful();

        $this->assertSame(2, NetworkCredential::where('network_id', 'mock-realtime')->count());
    }

    public function test_ttl_option_overrides_the_configured_default(): void
    {
        $this->artisan('gateway:terminal:issue-token', [
            'network_id' => 'mock-realtime',
            'terminal_id' => 'T-01',
            '--ttl' => 15,
        ])->assertSuccessful();

        $token = NetworkCredential::where('network_id', 'mock-realtime')->where('terminal_id', 'T-01')->sole()->tokens()->sole();

        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addMinutes(15)->addSecond()));
    }

    public function test_revoking_a_terminal_flips_its_status_and_deletes_its_tokens(): void
    {
        $this->artisan('gateway:terminal:issue-token', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-01'])->assertSuccessful();

        $this->artisan('gateway:terminal:revoke', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-01'])->assertSuccessful();

        $credential = NetworkCredential::where('network_id', 'mock-realtime')->where('terminal_id', 'T-01')->sole();
        $this->assertSame('revoked', $credential->status);
        $this->assertSame(0, $credential->tokens()->count());
    }

    public function test_revoking_does_not_affect_a_sibling_terminal_on_the_same_network(): void
    {
        $this->artisan('gateway:terminal:issue-token', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-01'])->assertSuccessful();
        $this->artisan('gateway:terminal:issue-token', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-02'])->assertSuccessful();

        $this->artisan('gateway:terminal:revoke', ['network_id' => 'mock-realtime', 'terminal_id' => 'T-01'])->assertSuccessful();

        $sibling = NetworkCredential::where('network_id', 'mock-realtime')->where('terminal_id', 'T-02')->sole();
        $this->assertSame('active', $sibling->status);
    }

    public function test_revoking_an_unknown_terminal_fails(): void
    {
        $this->artisan('gateway:terminal:revoke', ['network_id' => 'mock-realtime', 'terminal_id' => 'ghost'])->assertFailed();
    }
}
