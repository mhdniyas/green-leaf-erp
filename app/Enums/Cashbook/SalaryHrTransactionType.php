<?php

declare(strict_types=1);

namespace App\Enums\Cashbook;

enum SalaryHrTransactionType: string
{
    case Salary = 'salary';
    case SalaryAdvance = 'salary_advance';
    case SalaryAdjustment = 'salary_adjustment';
    case AdvanceRecovery = 'advance_recovery';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::SalaryAdvance => 'Salary Advance',
            self::SalaryAdjustment => 'Salary Adjustment',
            self::AdvanceRecovery => 'Advance Recovery',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Salary => 'Regular monthly salary disbursements for shop staff.',
            self::SalaryAdvance => 'Mid-month staff advances taken from shop or company funds.',
            self::SalaryAdjustment => 'Corrections, overtime balances, or payroll reconciliation adjustments.',
            self::AdvanceRecovery => 'Deductions or recovery of previously issued employee advances.',
        };
    }

    /**
     * @return array<int, string>
     */
    public function defaultAllowedModes(): array
    {
        return ['sales_cash', 'petty', 'company_payable'];
    }

    public function defaultPaymentMode(): string
    {
        return 'sales_cash';
    }
}
