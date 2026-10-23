<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Models\ProductUnit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use Illuminate\Validation\ValidationException;

class ShopInvoiceIntegrityValidator
{
    public function __construct(
        private readonly ApprovedDailyPriceResolver $approvedDailyPriceResolver,
    ) {}

    public function assertMatchesApprovedDailyPrices(ShopInvoice $invoice): void
    {
        $invoice->loadMissing(['shop.priceGroup', 'items.product']);

        if (! $invoice->shop) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice is not linked to a valid shop.',
            ]);
        }

        if ($invoice->items->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice has no billable items.',
            ]);
        }

        foreach ($invoice->items as $item) {
            $this->assertItemMatchesApprovedDailyPrice($invoice, $item);
        }
    }

    private function assertItemMatchesApprovedDailyPrice(ShopInvoice $invoice, ShopInvoiceItem $item): void
    {
        if (! $item->product) {
            throw ValidationException::withMessages([
                'invoice' => "Invoice item {$item->id} is not linked to a valid product.",
            ]);
        }

        $itemGrade = (string) ($item->product_grade ?? 'A');
        $approvedPrice = $this->approvedDailyPriceResolver->resolve(
            $item->product,
            $invoice->shop,
            $invoice->business_date,
            $itemGrade,
        );

        $invoicePrice = round((float) $item->unit_price, 2);
        $priceSource = (string) ($approvedPrice['source'] ?? 'normal');
        $productLabel = $itemGrade !== 'A' ? "{$item->product_name} (Grade {$itemGrade})" : $item->product_name;

        if (abs($invoicePrice - $approvedPrice['price']) > 0.0001) {
            throw ValidationException::withMessages([
                'invoice' => sprintf(
                    'Invoice price mismatch for %s. Invoice has %.2f but approved %s price is %.2f.',
                    $productLabel,
                    $invoicePrice,
                    $priceSource === 'special' ? 'special' : $approvedPrice['category_code'],
                    $approvedPrice['price'],
                ),
            ]);
        }

        $invoicePriceUnit = ProductUnit::normalizeUnit((string) ($item->price_unit ?: $item->unit));
        $approvedPriceUnit = ProductUnit::normalizeUnit((string) $approvedPrice['price_unit']);

        if ($invoicePriceUnit !== $approvedPriceUnit) {
            throw ValidationException::withMessages([
                'invoice' => sprintf(
                    'Invoice unit mismatch for %s. Invoice uses %s but approved daily price uses %s.',
                    $productLabel,
                    strtoupper($invoicePriceUnit),
                    strtoupper($approvedPriceUnit),
                ),
            ]);
        }
    }
}
