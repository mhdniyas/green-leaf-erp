<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Services\Purchasing\PurchaserReadCacheService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

enum TestGradeEnum: string
{
    case GradeA = 'A';
    case GradeB = 'B';
}

class PurchaserReadCacheServiceTest extends TestCase
{
    private PurchaserReadCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = new PurchaserReadCacheService(store: 'array');
    }

    public function test_remember_executes_callback_on_miss_and_caches_value(): void
    {
        $callbackCount = 0;
        $callback = function () use (&$callbackCount): array {
            $callbackCount++;

            return ['products' => ['Tomato', 'Potato']];
        };

        // First call: Miss -> invokes callback
        $res1 = $this->cacheService->remember(
            scopes: ['products'],
            dataset: 'catalog',
            ttlSeconds: 60,
            callback: $callback,
            tenantScope: 1,
            userId: 42,
            businessDate: '2026-08-25',
            grade: 'A',
            filters: ['search' => 'Tom']
        );

        $this->assertSame(['products' => ['Tomato', 'Potato']], $res1);
        $this->assertSame(1, $callbackCount);

        // Second call: Hit -> returns cached value, callback not executed
        $res2 = $this->cacheService->remember(
            scopes: ['products'],
            dataset: 'catalog',
            ttlSeconds: 60,
            callback: $callback,
            tenantScope: 1,
            userId: 42,
            businessDate: '2026-08-25',
            grade: 'A',
            filters: ['search' => 'Tom']
        );

        $this->assertSame(['products' => ['Tomato', 'Potato']], $res2);
        $this->assertSame(1, $callbackCount, 'Callback should not be executed on cache hit.');
    }

    public function test_key_structure_and_scope_isolation(): void
    {
        $date = Carbon::parse('2026-08-25');

        $key1 = $this->cacheService->buildKey(
            scopes: ['orders', 'carts'],
            dataset: 'summary_rows',
            tenantScope: 1,
            userId: 5,
            businessDate: $date,
            grade: 'A',
            filters: ['chip' => 'VEG']
        );

        $this->assertStringStartsWith('purchaser:v1:tenant_1:user_5:2026-08-25:A:summary_rows:', $key1);
        $this->assertStringEndsWith('carts_1:orders_1', $key1);

        // Default tenant scope
        $keyDefaultTenant = $this->cacheService->buildKey(
            scopes: 'orders',
            dataset: 'summary_rows',
            tenantScope: null,
            userId: 5,
            businessDate: $date,
            grade: 'A',
        );
        $this->assertStringStartsWith('purchaser:v1:single_company:user_5:', $keyDefaultTenant);

        // Grade isolation (A vs B)
        $keyGradeB = $this->cacheService->buildKey(
            scopes: ['orders', 'carts'],
            dataset: 'summary_rows',
            tenantScope: 1,
            userId: 5,
            businessDate: $date,
            grade: 'B',
            filters: ['chip' => 'VEG']
        );
        $this->assertNotSame($key1, $keyGradeB);
        $this->assertStringContainsString(':B:summary_rows:', $keyGradeB);

        // Filter isolation
        $keyOtherFilter = $this->cacheService->buildKey(
            scopes: ['orders', 'carts'],
            dataset: 'summary_rows',
            tenantScope: 1,
            userId: 5,
            businessDate: $date,
            grade: 'A',
            filters: ['chip' => 'Fruit']
        );
        $this->assertNotSame($key1, $keyOtherFilter);
    }

    public function test_multiple_dependency_scopes_invalidation(): void
    {
        $executionCount = 0;
        $fetcher = function () use (&$executionCount): string {
            $executionCount++;

            return 'daily_summary_run_'.$executionCount;
        };

        $scopes = ['orders', 'carts', 'prices', 'products', 'settings'];

        // Initial run
        $v1 = $this->cacheService->remember($scopes, 'daily_hub', 60, $fetcher, 'tenant_main');
        $this->assertSame('daily_summary_run_1', $v1);
        $this->assertSame(1, $executionCount);

        // Warm hit
        $vHit = $this->cacheService->remember($scopes, 'daily_hub', 60, $fetcher, 'tenant_main');
        $this->assertSame('daily_summary_run_1', $vHit);
        $this->assertSame(1, $executionCount);

        // Invalidate ONLY 'prices'
        $this->cacheService->invalidate('prices', 'tenant_main');

        // Must miss and recompute because 1 dependency scope version changed
        $v2 = $this->cacheService->remember($scopes, 'daily_hub', 60, $fetcher, 'tenant_main');
        $this->assertSame('daily_summary_run_2', $v2);
        $this->assertSame(2, $executionCount);

        // Invalidate ONLY 'settings'
        $this->cacheService->invalidate('settings', 'tenant_main');

        $v3 = $this->cacheService->remember($scopes, 'daily_hub', 60, $fetcher, 'tenant_main');
        $this->assertSame('daily_summary_run_3', $v3);
        $this->assertSame(3, $executionCount);
    }

    public function test_atomic_version_increment_and_persistence(): void
    {
        $vInit = $this->cacheService->getScopeVersion('orders', 'tenant_test');
        $this->assertSame(1, $vInit);

        $this->cacheService->invalidate('orders', 'tenant_test');
        $v2 = $this->cacheService->getScopeVersion('orders', 'tenant_test');
        $this->assertSame(2, $v2);

        $this->cacheService->invalidate('orders', 'tenant_test');
        $v3 = $this->cacheService->getScopeVersion('orders', 'tenant_test');
        $this->assertSame(3, $v3);

        // Reset local in-memory version cache to simulate fresh request reading from cache store
        $this->cacheService->resetLocalState();
        $this->assertSame(3, $this->cacheService->getScopeVersion('orders', 'tenant_test'), 'Version must persist across local state resets.');
    }

    public function test_recursive_nested_filter_canonicalization(): void
    {
        // Two nested filter structures with identical content but different key orders
        $filter1 = [
            'z_param' => 'last',
            'nested' => [
                'b' => 2,
                'a' => 1,
                'tags' => ['veg', 'fresh'],
                'date' => Carbon::parse('2026-08-25 10:00:00'),
                'active' => true,
                'grade' => TestGradeEnum::GradeA,
            ],
            'a_param' => 'first',
        ];

        $filter2 = [
            'a_param' => 'first',
            'nested' => [
                'grade' => TestGradeEnum::GradeA,
                'date' => Carbon::parse('2026-08-25 10:00:00'),
                'active' => true,
                'a' => 1,
                'tags' => ['veg', 'fresh'],
                'b' => 2,
            ],
            'z_param' => 'last',
        ];

        $hash1 = $this->cacheService->canonicalizeAndHashFilters($filter1);
        $hash2 = $this->cacheService->canonicalizeAndHashFilters($filter2);

        $this->assertSame($hash1, $hash2, 'Deeply nested associative arrays with different key order must yield identical hash.');
        $this->assertSame(32, strlen($hash1), 'Hash should be 32-character SHA-256 slice.');

        // List element ordering must be preserved (e.g. ['veg', 'fresh'] !== ['fresh', 'veg'])
        $filterDifferentList = $filter1;
        $filterDifferentList['nested']['tags'] = ['fresh', 'veg'];
        $hashDifferent = $this->cacheService->canonicalizeAndHashFilters($filterDifferentList);

        $this->assertNotSame($hash1, $hashDifferent, 'Different list element order must produce different hashes.');
    }

    public function test_redis_failure_falls_back_gracefully_and_executes_callback_exactly_once(): void
    {
        $failingService = new PurchaserReadCacheService(store: 'non_existent_redis_driver');

        $executionCount = 0;
        $fallback = function () use (&$executionCount): string {
            $executionCount++;

            return 'mysql_fresh_data';
        };

        $result = $failingService->remember(
            scopes: ['prices'],
            dataset: 'daily_prices',
            ttlSeconds: 60,
            callback: $fallback,
            tenantScope: 'single_company'
        );

        $this->assertSame('mysql_fresh_data', $result);
        $this->assertSame(1, $executionCount, 'Fallback callback must execute exactly once on Redis failure.');
    }

    public function test_redis_failure_logging_is_rate_limited(): void
    {
        $failingService = new PurchaserReadCacheService(store: 'invalid_store_name');

        $logCount = 0;
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message) use (&$logCount): bool {
                $logCount++;

                return $message === 'purchaser.read_cache.redis_failure';
            });

        // 5 consecutive failing calls on the same instance
        for ($i = 0; $i < 5; $i++) {
            $failingService->remember('suppliers', 'hub', 60, fn () => 'data_'.$i);
        }

        $this->assertSame(1, $logCount, 'Redis failure warnings must be rate-limited to prevent log spam.');
    }
}
