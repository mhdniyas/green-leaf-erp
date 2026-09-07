<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CashbookSettlementSummaryTest extends TestCase
{
    public function test_demo_uses_saved_formulas_for_net_balance_without_counting_custom_settlements_twice(): void
    {
        $script = <<<'JS'
import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
await import(pathToFileURL(process.argv[1]));
const calculate = globalThis.CashbookSettlementSummary.calculate;
assert.equal(globalThis.CashbookSettlementSummary.formatAmount(-15155), '-₹15,155.00');
assert.equal(globalThis.CashbookSettlementSummary.formatAmount(0), '₹0.00');
const relations = [
    { id: 1, name: 'Net Sales', kind: 'default_income', enabled: true, items: [{setting_id: 1, role: 'add'}, {setting_id: 2, role: 'subtract'}, {setting_id: 3, role: 'add'}] },
    { id: 2, name: 'Shop Costs', kind: 'default_expense', enabled: true, items: [{setting_id: 4, role: 'add'}] },
    { id: 3, name: 'Company Payable', is_company_payable: true, kind: 'formula', enabled: true, items: [{setting_id: 1, role: 'add'}, {setting_id: 4, role: 'subtract'}] },
    { id: 4, name: 'Hidden', kind: 'formula', enabled: false, items: [{setting_id: 1, role: 'add'}] },
];
const amounts = {1: 100.10, 2: 25.05, 3: 10.00, 4: 20.25, 999: 999999};
const result = calculate(relations, amounts, 10.00);
// Display settlements filter out default_income, default_expense, default_balance
assert.deepEqual(result.settlements.map(row => row.amount), [79.85]);
assert.equal(result.companyPayable.amount, 79.85);
assert.equal(result.verifiedPayments, 10.00);
assert.equal(result.netBalance, 69.85);
assert.equal(result.netLabel, 'Company Payable');

// Test ₹302,646 - ₹10,000 = ₹292,646
const casioRelations = [
    { id: 10, name: 'Company Payable', is_company_payable: true, kind: 'formula', enabled: true, items: [{setting_id: 101, role: 'add'}] }
];
const casioAmounts = {101: 302646.00};
const casioResult = calculate(casioRelations, casioAmounts, 10000.00);
assert.equal(casioResult.companyPayable.amount, 302646.00);
assert.equal(casioResult.verifiedPayments, 10000.00);
assert.equal(casioResult.netBalance, 292646.00);
assert.equal(casioResult.netLabel, 'Company Payable');
JS;
        $process = new Process(['node', '--input-type=module', '-e', $script, dirname(__DIR__, 2).'/public/js/cashbook-settlement-summary.js']);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
