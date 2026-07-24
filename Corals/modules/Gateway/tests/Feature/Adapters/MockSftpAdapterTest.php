<?php

namespace Tests\Feature\Adapters;

use Corals\Modules\Gateway\Adapters\MockSftp\MockSftpAdapter;
use Corals\Modules\Gateway\Models\Merchant;
use Corals\Modules\Gateway\Models\NetworkAdapter;
use Corals\Modules\Gateway\Models\PaymentIntent;
use Corals\Modules\Gateway\Models\PaymentReference;
use Corals\Modules\Gateway\Models\PosWallet;
use Corals\Modules\Gateway\Models\ReconciliationException;
use Corals\Modules\Gateway\Models\Transaction;
use Corals\Modules\Gateway\tests\GatewayTestCase;
use Illuminate\Support\Facades\Storage;

class MockSftpAdapterTest extends GatewayTestCase
{
    private const NETWORK = 'mock-sftp';

    private const MID = '000111222';

    private const FIXTURE = __DIR__.'/../../Fixtures/mock-sftp/settlement-batch.txt';

    private function setUpFixture(): void
    {
        NetworkAdapter::factory()->create([
            'network_id' => self::NETWORK,
            'archetype' => 'sftp',
            'config' => [],
        ]);

        $merchant = Merchant::factory()->create(['mid' => self::MID]);

        foreach (['1000000001', '1000000002', '1000000003'] as $ref) {
            $intent = PaymentIntent::factory()->create([
                'merchant_id' => $merchant->id,
                'state' => 'ACTIVE',
                'mode' => 'one_time',
                'amount_policy' => ['type' => 'variable', 'min' => 100, 'max' => 100000, 'allow_partial' => false],
                'overpay_policy' => 'reject',
                'underpay_policy' => 'reject',
            ]);
            PaymentReference::factory()->create([
                'payment_intent_id' => $intent->id,
                'reference_token' => $ref,
                'status' => 'active',
                'expires_at' => now()->addHour(),
            ]);
        }

        PosWallet::factory()->create([
            'network_id' => self::NETWORK,
            'external_store_id' => 'S-SFTP',
            'balance_centavos' => 100000,
            'reserved_centavos' => 0,
            'status' => 'active',
        ]);
    }

    public function test_dropped_file_confirms_good_rows_and_excepts_the_malformed_one(): void
    {
        $this->setUpFixture();
        $adapter = new MockSftpAdapter();

        $result = $adapter->ingest(self::NETWORK, 'BATCH-001', file_get_contents(self::FIXTURE));

        $this->assertSame(2, $result['confirmed_count']);
        $this->assertSame(1, $result['exception_count']);
        $this->assertSame('NTX-SFTP-003', $result['exceptions'][0]['network_txn_id']);
        $this->assertSame(2, Transaction::where('state', 'CONFIRMED')->where('finality', 'batch')->count());
        $this->assertSame(1, ReconciliationException::count());
    }

    public function test_poll_reads_every_file_on_the_disk_then_removes_it(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sftp/mock-sftp/inbound/BATCH-002.txt', file_get_contents(self::FIXTURE));
        $this->setUpFixture();
        $adapter = new MockSftpAdapter();

        $results = $adapter->poll('local', 'sftp/mock-sftp/inbound', self::NETWORK);

        $this->assertCount(1, $results);
        $this->assertSame(2, $results[0]['confirmed_count']);
        Storage::disk('local')->assertMissing('sftp/mock-sftp/inbound/BATCH-002.txt');
    }

    public function test_malformed_row_does_not_abort_the_rest_of_the_batch(): void
    {
        $this->setUpFixture();
        $adapter = new MockSftpAdapter();

        $malformedFirst = "NTX-SFTP-003|000111222|1000000003|bad-amount|1739700200\nNTX-SFTP-001|000111222|1000000001|10000|1739700000\n";

        $result = $adapter->ingest(self::NETWORK, 'BATCH-003', $malformedFirst);

        $this->assertSame(1, $result['confirmed_count']);
        $this->assertSame(1, $result['exception_count']);
        $this->assertSame('CONFIRMED', Transaction::where('network_txn_id', 'NTX-SFTP-001')->first()->state);
    }
}
