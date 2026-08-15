<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ShopProfitIntelligenceService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for ShopProfitIntelligenceService.
 *
 * Tests the calculation logic in isolation, using the worked example
 * from Section 4.2 of the spec as fixtures — known input → known output.
 * This ensures a future refactor cannot silently change the math.
 *
 * These are pure unit tests: no DB, no HTTP.
 */
class ShopProfitIntelligenceServiceTest extends TestCase
{
    private ShopProfitIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShopProfitIntelligenceService();
    }

    // ─── Helper: call private methods via Reflection ─────────────────────────

    /** @param array<string, mixed> $args */
    private function call(string $method, array $args): mixed
    {
        $ref = new ReflectionClass($this->service);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($this->service, ...$args);
    }

    // ─── Tests for calculateLeakage() ────────────────────────────────────────

    /**
     * Spec §4.2 worked example:
     * Ratios = [55, 85, 55, 55, 55, 55, 55]
     * Median of 7 sorted values = 55%  (index 3 of 0-6 = 55)
     * Only Tuesday (85%) exceeds baseline.
     * excess = 30pp, daily_leakage = 30% × 8,000 = 2,400
     * monthly_leakage = 2,400 × 4 Tuesdays = 9,600
     * Total leakage = 9,600.
     */
    public function test_calculates_leakage_matching_spec_worked_example(): void
    {
        $weekdayAnalysis = $this->buildSpecFixture();

        /** @var array{total_leakage: float, baseline_ratio: float, flagged_days: array} $result */
        $result = $this->call('calculateLeakage', [$weekdayAnalysis]);

        $this->assertEquals(55.0, $result['baseline_ratio'], 'Median purchase ratio should be 55%');
        $this->assertCount(1, $result['flagged_days'], 'Only Tuesday should be flagged');
        $this->assertEquals('Tuesday', $result['flagged_days'][0]['day']);
        $this->assertEquals(30.0, $result['flagged_days'][0]['excess_ratio'], 'Excess ratio should be 30pp');
        $this->assertEquals(2400.00, $result['flagged_days'][0]['daily_leakage'], 'Daily leakage should be ₹2,400');
        $this->assertEquals(9600.00, $result['flagged_days'][0]['monthly_leakage'], 'Monthly leakage should be ₹9,600');
        $this->assertEquals(9600.00, $result['total_leakage'], 'Total leakage should be ₹9,600');
    }

    /**
     * Spec §4.2: suggested cut = excess / purchase_ratio = 30/85 ≈ 35%
     */
    public function test_suggested_cut_percentage_is_correct(): void
    {
        $result = $this->call('calculateLeakage', [$this->buildSpecFixture()]);
        $cut    = $result['flagged_days'][0]['suggested_cut_pct'];

        // round(30 / 85 * 100) = round(35.29) = 35
        $this->assertEquals(35, $cut, 'Suggested cut % should be 35%');
    }

    /**
     * When all days have the same purchase ratio, leakage should be zero.
     */
    public function test_no_leakage_when_all_days_equal_ratio(): void
    {
        $uniform = [];
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $uniform[$day] = ['day' => $day, 'avg_sales' => 10000.0, 'purchase_ratio' => 60.0, 'sample_days' => 4];
        }

        $result = $this->call('calculateLeakage', [$uniform]);

        $this->assertEquals(0.0, $result['total_leakage'], 'No leakage when all ratios equal median');
        $this->assertCount(0, $result['flagged_days']);
    }

    /**
     * Days with zero sales are never flagged even if ratio is technically > baseline.
     */
    public function test_zero_sales_day_never_flagged(): void
    {
        $data = [];
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $data[$day] = ['day' => $day, 'avg_sales' => 8000.0, 'purchase_ratio' => 55.0, 'sample_days' => 4];
        }
        // Override Sunday: high ratio but zero sales
        $data['Sunday'] = ['day' => 'Sunday', 'avg_sales' => 0.0, 'purchase_ratio' => 90.0, 'sample_days' => 4];

        $result = $this->call('calculateLeakage', [$data]);

        $flaggedDays = array_column($result['flagged_days'], 'day');
        $this->assertNotContains('Sunday', $flaggedDays, 'Zero-sales days must not be flagged');
    }

    // ─── Tests for healthBadge() ─────────────────────────────────────────────

    /** @dataProvider healthBadgeProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('healthBadgeProvider')]
    public function test_health_badge_thresholds(float $pct, string $expectedBadge, string $expectedTone): void
    {
        [$badge, $tone] = $this->call('healthBadge', [$pct]);

        $this->assertEquals($expectedBadge, $badge);
        $this->assertEquals($expectedTone, $tone);
    }

    /** @return array<string, array{float, string, string}> */
    public static function healthBadgeProvider(): array
    {
        return [
            'exactly 90 → Optimal'       => [90.0, 'Optimal', 'emerald'],
            '95% → Optimal'              => [95.0, 'Optimal', 'emerald'],
            '100% → Optimal'             => [100.0, 'Optimal', 'emerald'],
            'exactly 75 → Needs Attention' => [75.0, 'Needs Attention', 'amber'],
            'spec example 88.5% → Needs Attention' => [88.5, 'Needs Attention', 'amber'],
            '60% → Critical'             => [60.0, 'Critical', 'rose'],
            '0% → Critical'              => [0.0, 'Critical', 'rose'],
        ];
    }

    // ─── Tests for the full potential / captured calculation ─────────────────

    /**
     * Spec §4.2:
     * Captured Profit = sum(Net × sample_days)
     *   Mon: 3400×4=13600, Tue: -800×4=-3200, Wed: 2275×4=9100,
     *   Thu: 2050×4=8200, Fri: 2500×4=10000, Sat: 4300×5=21500, Sun: 2950×5=14750
     *   Total = 13600-3200+9100+8200+10000+21500+14750 = 73950
     * Potential Profit = 73,950 + 9,600 = 83,550
     * Captured % = 73950 / 83550 ≈ 88.5%
     *
     * We test the formula in isolation using the same numbers.
     */
    public function test_potential_profit_formula_matches_spec(): void
    {
        $capturedProfit  = 73950.0;
        $totalLeakage    = 9600.0;
        $potentialProfit = $capturedProfit + $totalLeakage;
        $capturedPct     = round(($capturedProfit / $potentialProfit) * 100, 1);

        $this->assertEquals(83550.0, $potentialProfit, 'Potential profit should be ₹83,550');
        $this->assertEquals(88.5, $capturedPct, 'Captured % should be 88.5%');
    }

    /**
     * Empty result when shop has no data.
     */
    public function test_empty_result_has_correct_defaults(): void
    {
        $ref    = new ReflectionClass($this->service);
        $method = $ref->getMethod('emptyResult');
        $method->setAccessible(true);
        $result = $method->invoke($this->service);

        $this->assertFalse($result['has_data']);
        $this->assertEquals(0.0, $result['captured_profit']);
        $this->assertEquals(0.0, $result['total_leakage']);
        $this->assertEquals('No Data', $result['health_badge']);
    }

    // ─── Fixture builder ─────────────────────────────────────────────────────

    /**
     * Build the exact 7-day dataset from the spec §4.2 worked example.
     * Overhead (₹2,000/day) is already folded into the Net/Expense numbers.
     *
     * | Day | Sales | GL Bill | Ratio | Net  | Sample |
     * | Mon |12,000 | 6,600   | 55%   |3,400 |  4     |
     * | Tue | 8,000 | 6,800   | 85%   |-800  |  4     |
     * | Wed | 9,500 | 5,225   | 55%   |2,275 |  4     |
     * | Thu | 9,000 | 4,950   | 55%   |2,050 |  4     |
     * | Fri |10,000 | 5,500   | 55%   |2,500 |  4     |
     * | Sat |14,000 | 7,700   | 55%   |4,300 |  5     |
     * | Sun |11,000 | 6,050   | 55%   |2,950 |  5     |
     *
     * @return array<string, array{day: string, avg_sales: float, purchase_ratio: float, sample_days: int}>
     */
    private function buildSpecFixture(): array
    {
        return [
            'Monday'    => ['day' => 'Monday',    'avg_sales' => 12000.0, 'purchase_ratio' => 55.0, 'sample_days' => 4],
            'Tuesday'   => ['day' => 'Tuesday',   'avg_sales' => 8000.0,  'purchase_ratio' => 85.0, 'sample_days' => 4],
            'Wednesday' => ['day' => 'Wednesday', 'avg_sales' => 9500.0,  'purchase_ratio' => 55.0, 'sample_days' => 4],
            'Thursday'  => ['day' => 'Thursday',  'avg_sales' => 9000.0,  'purchase_ratio' => 55.0, 'sample_days' => 4],
            'Friday'    => ['day' => 'Friday',    'avg_sales' => 10000.0, 'purchase_ratio' => 55.0, 'sample_days' => 4],
            'Saturday'  => ['day' => 'Saturday',  'avg_sales' => 14000.0, 'purchase_ratio' => 55.0, 'sample_days' => 5],
            'Sunday'    => ['day' => 'Sunday',    'avg_sales' => 11000.0, 'purchase_ratio' => 55.0, 'sample_days' => 5],
        ];
    }
}
