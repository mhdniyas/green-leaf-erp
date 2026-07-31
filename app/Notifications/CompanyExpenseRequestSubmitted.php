<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ShopAccountingEntryLine;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyExpenseRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ShopAccountingEntryLine $line,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->line->loadMissing(['entry.shop', 'category']);
        $shop = $this->line->entry?->shop;
        $amount = round((float) ($this->line->company_payable_amount ?? $this->line->amount), 2);

        return [
            'kind' => 'company_expense_request_submitted',
            'title' => 'New Company Expense Request',
            'message' => sprintf(
                'Shop: %s | Category: %s | Amount: ₹%s | Date: %s',
                $shop?->name ?? 'Shop',
                $this->line->category?->name ?? 'Expense',
                number_format($amount, 2),
                $this->line->entry?->business_date?->format('d M Y') ?? now()->format('d M Y'),
            ),
            'shop_name' => $shop?->name,
            'category' => $this->line->category?->name,
            'amount' => $amount,
            'business_date' => $this->line->entry?->business_date?->toDateString(),
            'route' => route('admin.finance-v2.company-payables.show', ['line' => $this->line->id]),
        ];
    }
}
