<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardNotificationService
{
    /**
     * @return array<string, int>
     */
    public function counts(?CarbonInterface $date = null): array
    {
        $businessDate = Carbon::parse($date ?? today())->toDateString();

        $counts = [
            'owned_shop_receipts_pending' => ShopAccountingEntry::query()
                ->where('status', 'submitted')
                ->count(),
            'owned_shop_recheck' => ShopAccountingEntry::query()
                ->where('status', 'recheck_required')
                ->count(),
            'owned_shop_company_payments_pending' => ShopCredit::query()
                ->where('type', 'out')
                ->where('status', 'pending')
                ->count(),
            'shop_payment_requests_pending' => ShopInvoicePaymentRequest::query()
                ->where('status', 'pending')
                ->count(),
            'shop_orders_pending' => ShopOrder::query()
                ->whereDate('business_date', $businessDate)
                ->whereIn('state', ['submitted', 'update_requested'])
                ->count(),
            'delivery_reviews_pending' => ShopInvoice::query()
                ->whereHas('order', fn ($query) => $query->where('delivery_review_status', 'pending'))
                ->count(),
            'grn_approvals_pending' => GoodsReceived::query()
                ->whereIn('status', ['pending_approval', 'recheck_required'])
                ->count(),
            'supplier_invoices_pending' => PurchaseInvoice::query()
                ->where('status', InvoiceStatus::Pending)
                ->count(),
            'company_payables_pending' => Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_status')
                ? ShopAccountingEntryLine::query()
                    ->where('funding_source', 'company')
                    ->where('company_payable_status', 'pending')
                    ->count()
                : 0,
        ];

        $counts['accounting_total'] = $counts['owned_shop_receipts_pending']
            + $counts['owned_shop_recheck']
            + $counts['owned_shop_company_payments_pending']
            + $counts['shop_payment_requests_pending']
            + $counts['company_payables_pending'];

        $counts['owned_shop_total'] = $counts['owned_shop_receipts_pending']
            + $counts['owned_shop_recheck']
            + $counts['owned_shop_company_payments_pending'];

        $counts['purchasing_total'] = $counts['shop_orders_pending']
            + $counts['delivery_reviews_pending']
            + $counts['grn_approvals_pending']
            + $counts['supplier_invoices_pending'];

        return $counts;
    }

    /**
     * @return list<array{label: string, count: int, href: string, hint: string, tone: string}>
     */
    public function adminActionItems(?CarbonInterface $date = null): array
    {
        $businessDate = Carbon::parse($date ?? today())->toDateString();
        $counts = $this->counts($date);

        return [
            [
                'label' => 'Client Shop Receipts',
                'count' => $counts['owned_shop_receipts_pending'],
                'href' => route('admin.accounting.owned-shops.index'),
                'hint' => 'Daily shop receipts waiting for accounting approval.',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Shop Payments',
                'count' => $counts['shop_payment_requests_pending'],
                'href' => route('admin.accounting.daily-sales', ['date' => $businessDate]),
                'hint' => 'Payments to company waiting for accounting approval.',
                'tone' => 'cyan',
            ],
            [
                'label' => 'Company Cash Requests',
                'count' => $counts['owned_shop_company_payments_pending'],
                'href' => route('admin.accounting.owned-shops.index'),
                'hint' => 'Owned shop cash payments waiting for approval.',
                'tone' => 'amber',
            ],
            [
                'label' => 'Company Expense Requests',
                'count' => $counts['company_payables_pending'],
                'href' => route('admin.finance-v2.company-payables.index'),
                'hint' => 'Shop expenses waiting for company payable review.',
                'tone' => 'violet',
            ],
            [
                'label' => 'Delivery Reviews',
                'count' => $counts['delivery_reviews_pending'],
                'href' => route('purchasing.shop-invoices.index'),
                'hint' => 'Shop invoices waiting for final delivered quantity review.',
                'tone' => 'rose',
            ],
            [
                'label' => 'Purchase Approvals',
                'count' => $counts['shop_orders_pending'],
                'href' => route('requisitions.board', ['date' => $businessDate]),
                'hint' => 'Shop orders waiting in the purchasing board.',
                'tone' => 'violet',
            ],
            [
                'label' => 'GRN / Supplier Bills',
                'count' => $counts['grn_approvals_pending'] + $counts['supplier_invoices_pending'],
                'href' => route('purchasing.grns.index', ['date' => $businessDate]),
                'hint' => 'Goods receipts and supplier invoices needing review.',
                'tone' => 'slate',
            ],
        ];
    }
}
