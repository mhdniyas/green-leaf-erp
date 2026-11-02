<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class VendorPurchaseReportExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array{
     *     filters: array<string, mixed>,
     *     summary: object,
     *     vendors: Collection<int, object>,
     *     invoices: Collection<int, object>
     * }  $reportData
     */
    public function __construct(
        private readonly array $reportData,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        return [
            new class($this->reportData) implements FromArray, ShouldAutoSize, WithStyles, WithTitle
            {
                /**
                 * @param  array{
                 *     filters: array<string, mixed>,
                 *     summary: object,
                 *     vendors: Collection<int, object>,
                 *     invoices: Collection<int, object>
                 * }  $reportData
                 */
                public function __construct(private readonly array $reportData) {}

                public function title(): string
                {
                    return 'Vendor Summary';
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true, 'size' => 13]],
                        6 => ['font' => ['bold' => true]],
                    ];
                }

                /**
                 * @return array<int, array<int, mixed>>
                 */
                public function array(): array
                {
                    $filters = $this->reportData['filters'];
                    $vendors = $this->reportData['vendors'];

                    $periodRange = $filters['formatted_range'] ?? ($filters['start_date'].' – '.$filters['end_date']);
                    $purchaserFilter = ! empty($filters['purchaser_id']) ? 'Purchaser ID #'.$filters['purchaser_id'] : 'All Purchasers';
                    $productFilter = ! empty($filters['product_filter']) ? 'Product Filter: '.$filters['product_filter'] : 'All Products';
                    $paymentFilter = ($filters['payment'] ?? 'all') !== 'all' ? ucfirst((string) $filters['payment']) : 'All Modes';

                    $sheetRows = [
                        ['Green Leaf ERP — Vendor Purchase Report'],
                        ['Period:', $periodRange],
                        ['Purchaser Scope:', $purchaserFilter],
                        ['Product Scope:', $productFilter, 'Payment Filter:', $paymentFilter],
                        [],
                        [
                            'SL No',
                            'Name',
                            'Amount',
                            'Paid',
                            'Balance',
                        ],
                    ];

                    $totalPurchase = 0.0;
                    $totalPaid = 0.0;
                    $totalOutstanding = 0.0;
                    $sl = 1;

                    foreach ($vendors as $vendor) {
                        $purchase = round((float) ($vendor->total_purchase ?? 0), 2);
                        $cash = (float) ($vendor->cash_purchase ?? 0);
                        $credit = (float) ($vendor->credit_purchase ?? 0);
                        if ($credit <= 0.009) {
                            $paid = (float) ($vendor->paid_amount ?? $vendor->cash_purchase);
                        } elseif ($cash <= 0.009) {
                            $paid = (float) ($vendor->paid_amount ?? $vendor->credit_paid);
                        } else {
                            $paid = (float) ($vendor->paid_amount ?? ($cash + (float) $vendor->credit_paid));
                        }
                        $paid = round($paid, 2);
                        $outstanding = round((float) ($vendor->outstanding ?? 0), 2);

                        $totalPurchase += $purchase;
                        $totalPaid += $paid;
                        $totalOutstanding += $outstanding;

                        $sheetRows[] = [
                            $sl++,
                            (string) ($vendor->supplier_name ?? 'Unassigned Vendor'),
                            $purchase,
                            $paid,
                            $outstanding,
                        ];
                    }

                    $sheetRows[] = [];
                    $sheetRows[] = [
                        'TOTAL',
                        '',
                        round($totalPurchase, 2),
                        round($totalPaid, 2),
                        round($totalOutstanding, 2),
                    ];

                    return $sheetRows;
                }
            },
            new class($this->reportData) implements FromArray, ShouldAutoSize, WithStyles, WithTitle
            {
                /**
                 * @param  array{
                 *     filters: array<string, mixed>,
                 *     summary: object,
                 *     vendors: Collection<int, object>,
                 *     invoices: Collection<int, object>
                 * }  $reportData
                 */
                public function __construct(private readonly array $reportData) {}

                public function title(): string
                {
                    return 'Invoice Details';
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true, 'size' => 13]],
                        4 => ['font' => ['bold' => true]],
                    ];
                }

                /**
                 * @return array<int, array<int, mixed>>
                 */
                public function array(): array
                {
                    $filters = $this->reportData['filters'];
                    $invoices = $this->reportData['invoices'];

                    $periodRange = $filters['formatted_range'] ?? ($filters['start_date'].' – '.$filters['end_date']);

                    $sheetRows = [
                        ['Green Leaf ERP — Qualifying Purchase Bills & Invoices'],
                        ['Period:', $periodRange],
                        [],
                        [
                            'Date',
                            'Invoice / Bill #',
                            'Vendor',
                            'Purchaser',
                            'Payment Mode',
                            'Purchase Total (₹)',
                            'Paid (₹)',
                            'Discount (₹)',
                            'Credit Balance (₹)',
                            'Status',
                        ],
                    ];

                    $totalPurchase = 0.0;
                    $totalPaid = 0.0;
                    $totalDiscount = 0.0;
                    $totalOutstanding = 0.0;

                    foreach ($invoices as $inv) {
                        $purchase = round((float) ($inv->total_purchase ?? 0), 2);
                        $paid = round((float) ($inv->paid_amount ?? 0), 2);
                        $discount = round((float) ($inv->discount ?? 0), 2);
                        $outstanding = round((float) ($inv->outstanding ?? 0), 2);
                        $paymentClass = strtolower((string) ($inv->payment_class ?? 'cash'));

                        $totalPurchase += $purchase;
                        $totalPaid += $paid;
                        $totalDiscount += $discount;
                        $totalOutstanding += $outstanding;

                        $status = match (true) {
                            $paymentClass === 'cash' => 'Paid',
                            $outstanding <= 0.009 => 'Paid',
                            $paid > 0.009 => 'Partial',
                            default => 'Unpaid',
                        };

                        $sheetRows[] = [
                            (string) ($inv->business_date ?? ''),
                            (string) ($inv->invoice_number ?? ''),
                            (string) ($inv->supplier_name ?? ''),
                            (string) ($inv->purchaser_name ?? ''),
                            ucfirst($paymentClass),
                            $purchase,
                            $paid,
                            $discount,
                            $outstanding,
                            $status,
                        ];
                    }

                    $sheetRows[] = [];
                    $sheetRows[] = [
                        'TOTAL',
                        '',
                        '',
                        '',
                        '',
                        round($totalPurchase, 2),
                        round($totalPaid, 2),
                        round($totalDiscount, 2),
                        round($totalOutstanding, 2),
                        '',
                    ];

                    return $sheetRows;
                }
            },
        ];
    }
}
