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
    { id: 3, name: 'Company Payable', kind: 'formula', enabled: true, items: [{setting_id: 1, role: 'add'}, {setting_id: 4, role: 'subtract'}] },
    { id: 4, name: 'Hidden', kind: 'formula', enabled: false, items: [{setting_id: 1, role: 'add'}] },
];
const amounts = {1: 100.10, 2: 25.05, 3: 10.00, 4: 20.25, 999: 999999};
const result = calculate(relations, amounts);
assert.deepEqual(result.settlements.map(row => row.amount), [85.05, 20.25, 79.85]);
assert.equal(result.income, 85.05);
assert.equal(result.expense, 20.25);
assert.equal(result.netBalance, 64.80);
assert.equal(result.netLabel, 'Net Sales − Shop Costs');
assert.deepEqual(amounts, {1: 100.10, 2: 25.05, 3: 10.00, 4: 20.25, 999: 999999});
assert.equal(calculate(relations, {...amounts, 4: 100}).netBalance, -14.95);
assert.equal(calculate(relations, {}).netBalance, 0);
assert.equal(calculate([], amounts).netBalance, 0);
assert.deepEqual(calculate([], amounts).settlements, []);
assert.equal(calculate(relations.map(row => ({...row, enabled: false})), amounts).netBalance, 0);
assert.equal(calculate(relations.map(row => row.id === 2 ? {...row, enabled: false} : row), amounts).netBalance, 85.05);
assert.equal(calculate(relations, {1: 0.10, 2: 0, 3: 0.20, 4: 0}).netBalance, 0.30);
assert.equal(calculate(relations, {1: -50, 2: 10}).netBalance, -10);
const day2 = calculate(relations, {1: 50, 2: 5, 4: 10});
assert.equal(day2.netBalance, 35);
const balanceRelations = [
    { id: 10, name: 'Balance', kind: 'default_balance', enabled: true, items: [{setting_id: 1, role: 'add'}, {setting_id: 2, role: 'subtract'}, {setting_id: 4, role: 'subtract'}] },
    { id: 20, name: 'Company Payable', kind: 'default_company_payable', enabled: true, items: [{setting_id: 1, role: 'add'}, {setting_id: 4, role: 'subtract'}] },
];
const balanceResult = calculate(balanceRelations, amounts);
assert.equal(balanceResult.netBalance, 54.80);
assert.equal(balanceResult.netLabel, 'Balance');
assert.deepEqual(balanceResult.settlements.map(row => row.amount), [54.80, 79.85]);
JS;
        $process = new Process(['node', '--input-type=module', '-e', $script, dirname(__DIR__, 2).'/public/js/cashbook-settlement-summary.js']);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
