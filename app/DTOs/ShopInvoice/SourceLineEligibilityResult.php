<?php

declare(strict_types=1);

namespace App\DTOs\ShopInvoice;

use App\DTOs\BaseDTO;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use Illuminate\Support\Collection;

class SourceLineEligibilityResult extends BaseDTO
{
    /**
     * @param  Collection<int, ShopInvoice>  $lockedInvoices
     * @param  Collection<int, ShopInvoiceItem>  $lockedInvoiceItems
     * @param  array<int, ValidatedSourceLineItem>  $validatedLines
     */
    public function __construct(
        public Shop $shop,
        public Collection $lockedInvoices,
        public Collection $lockedInvoiceItems,
        public array $validatedLines,
        public float $totalCreditAmount,
        public float $totalDebitAmount,
        public float $netAdjustmentAmount,
        public ShopCreditNoteDirection $overallDirection,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toPreparedCreditNoteItems(): array
    {
        return array_map(
            fn (ValidatedSourceLineItem $line): array => $line->toCreditNoteItemPayload(),
            $this->validatedLines
        );
    }
}
