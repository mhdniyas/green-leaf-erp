<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\WastageEntry;
use App\Services\Finance\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WastageJournalRoundingTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('wastageAmounts')]
    public function test_wastage_posts_balanced_rounded_amounts(string $quantity, string $cost, string $expected): void
    {
        $wastage = WastageEntry::factory()->create([
            'batch_id' => null,
            'quantity' => $quantity,
            'cost_per_kg' => $cost,
        ]);

        $service = app(JournalService::class);
        $entry = $service->recordWastage($wastage);

        $this->assertCount(2, $entry->transactions);
        foreach (['debit' => '5200', 'credit' => '5100'] as $type => $code) {
            $transaction = $entry->transactions->firstWhere('type', $type);
            $this->assertNotNull($transaction);
            $this->assertSame($expected, $transaction->amount);
            $this->assertSame($code, $transaction->account->code);
        }
        $this->assertSame(WastageEntry::class, $entry->source_type);
        $this->assertSame($wastage->id, $entry->source_id);
        $this->assertSame('wastage', $entry->source_event);
        $this->assertSame($entry->id, $service->recordWastage($wastage)->id);
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame($quantity, $wastage->fresh()->quantity);
        $this->assertSame($cost, $wastage->fresh()->cost_per_kg);
    }

    public static function wastageAmounts(): array
    {
        return [
            'round up' => ['0.333', '12.3456', '4.11'],
            'round down' => ['0.123', '12.3456', '1.52'],
            'half cent' => ['0.500', '2.0100', '1.01'],
            'exact cents' => ['2.000', '12.3400', '24.68'],
        ];
    }

    public function test_manual_journals_still_reject_fractional_cents(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->firstOrCreate(
            ['code' => '5200'],
            ['name' => 'Wastage Expense', 'type' => 'expense', 'is_active' => true],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Journal line 0 amount must have no more than two decimal places.');

        app(JournalService::class)->createEntry(new JournalEntryData(
            entryDate: '2026-08-27',
            reference: 'PRECISION-TEST',
            description: 'Invalid precision',
            lines: [
                ['account_id' => $account->id, 'type' => 'debit', 'amount' => 1.005],
                ['account_id' => $account->id, 'type' => 'credit', 'amount' => 1.005],
            ],
        ), $user->id);
    }
}
