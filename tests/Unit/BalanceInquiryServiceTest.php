<?php

namespace Tests\Unit;

use App\Services\AccountBalanceService;
use App\Services\BalanceInquiryService;
use PHPUnit\Framework\TestCase;

class BalanceInquiryServiceTest extends TestCase
{
    public function test_build_reply_includes_total_accounts_and_latest_transactions(): void
    {
        $service = new BalanceInquiryService(new AccountBalanceService());

        $reply = $service->buildReply(
            'Tenant Demo',
            180000,
            [
                ['name' => 'Cash', 'balance' => 120000, 'is_default' => true],
                ['name' => 'BCA', 'balance' => 60000, 'is_default' => false],
            ],
            [
                [
                    'date' => '24 Jun',
                    'type' => 'INCOME',
                    'amount' => 50000,
                    'description' => 'Bonus',
                    'category' => 'Pemasukan Keluarga',
                    'source_account' => null,
                    'destination_account' => 'Cash',
                ],
                [
                    'date' => '23 Jun',
                    'type' => 'TRANSFER',
                    'amount' => 20000,
                    'description' => 'Pindah saldo',
                    'category' => null,
                    'source_account' => 'Cash',
                    'destination_account' => 'BCA',
                ],
            ],
        );

        $this->assertStringContainsString('Saldo total: Rp 180.000', $reply);
        $this->assertStringContainsString('- Cash (default): Rp 120.000', $reply);
        $this->assertStringContainsString('- BCA: Rp 60.000', $reply);
        $this->assertStringContainsString('5 transaksi terbaru:', $reply);
        $this->assertStringContainsString('24 Jun · INCOME · Rp 50.000 · Bonus · Pemasukan Keluarga · Cash', $reply);
        $this->assertStringContainsString('23 Jun · TRANSFER · Rp 20.000 · Pindah saldo · Cash -> BCA', $reply);
    }
}
