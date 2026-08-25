<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurchaserReadCacheService
{
    public const string STORE = 'redis';

    private const string PREFIX = 'purchaser:v1';

    public const string DEFAULT_TENANT_SCOPE = 'single_company';

    private const float FAILURE_LOG_INTERVAL_SECONDS = 60.0;

    /** @var array<string, int> In-memory request cache for scope versions */
    private array $localScopeVersions = [];

    private float $lastFailureLogAt = 0.0;

    public function __construct(
        private readonly string $store = self::STORE,
    ) {}

    /**
     * Cache or resolve a computed read payload using Redis with graceful fallback.
     *
     * @template T
     *
     * @param  string|array<int, string>  $scopes  Invalidation scopes this dataset depends on (e.g. ['orders', 'carts', 'prices'])
     * @param  Closure(): T  $callback
     * @param  int|string|null  $tenantScope  Specific tenant/company scope or null for DEFAULT_TENANT_SCOPE ('single_company')
     * @param  array<string, mixed>|string|null  $filters
     * @return T
     */
    public function remember(
        string|array $scopes,
        string $dataset,
        int $ttlSeconds,
        Closure $callback,
        int|string|null $tenantScope = null,
        ?int $userId = null,
        Carbon|string|null $businessDate = null,
        ?string $grade = null,
        array|string|null $filters = null,
    ): mixed {
        try {
            $key = $this->buildKey(
                scopes: $scopes,
                dataset: $dataset,
                tenantScope: $tenantScope,
                userId: $userId,
                businessDate: $businessDate,
                grade: $grade,
                filters: $filters,
            );

            return Cache::store($this->store)->remember($key, $ttlSeconds, $callback);
        } catch (Throwable $e) {
            $this->logRedisFailure($e, 'remember', [
                'scopes' => $scopes,
                'dataset' => $dataset,
                'tenant_scope' => $tenantScope,
            ]);

            return $callback();
        }
    }

    /**
     * Retrieve an item from the cache.
     *
     * @param  string|array<int, string>  $scopes
     * @param  array<string, mixed>|string|null  $filters
     */
    public function get(
        string|array $scopes,
        string $dataset,
        int|string|null $tenantScope = null,
        ?int $userId = null,
        Carbon|string|null $businessDate = null,
        ?string $grade = null,
        array|string|null $filters = null,
    ): mixed {
        try {
            $key = $this->buildKey(
                scopes: $scopes,
                dataset: $dataset,
                tenantScope: $tenantScope,
                userId: $userId,
                businessDate: $businessDate,
                grade: $grade,
                filters: $filters,
            );

            return Cache::store($this->store)->get($key);
        } catch (Throwable $e) {
            $this->logRedisFailure($e, 'get', [
                'scopes' => $scopes,
                'dataset' => $dataset,
            ]);

            return null;
        }
    }

    /**
     * Store an item in the cache.
     *
     * @param  string|array<int, string>  $scopes
     * @param  array<string, mixed>|string|null  $filters
     */
    public function put(
        string|array $scopes,
        string $dataset,
        mixed $value,
        int $ttlSeconds,
        int|string|null $tenantScope = null,
        ?int $userId = null,
        Carbon|string|null $businessDate = null,
        ?string $grade = null,
        array|string|null $filters = null,
    ): bool {
        try {
            $key = $this->buildKey(
                scopes: $scopes,
                dataset: $dataset,
                tenantScope: $tenantScope,
                userId: $userId,
                businessDate: $businessDate,
                grade: $grade,
                filters: $filters,
            );

            return Cache::store($this->store)->put($key, $value, $ttlSeconds);
        } catch (Throwable $e) {
            $this->logRedisFailure($e, 'put', [
                'scopes' => $scopes,
                'dataset' => $dataset,
            ]);

            return false;
        }
    }

    /**
     * Invalidate one or more scopes by atomically incrementing their integer version via Redis INCR.
     * Version keys do not expire (stored forever).
     *
     * @param  string|array<int, string>  $scopes
     */
    public function invalidate(string|array $scopes, int|string|null $tenantScope = null): void
    {
        $scopeList = is_array($scopes) ? $scopes : [$scopes];
        $resolvedTenant = $this->resolveTenantScope($tenantScope);

        foreach ($scopeList as $scope) {
            $versionKey = $this->versionKey($scope, $resolvedTenant);

            try {
                $store = Cache::store($this->store);
                $newVersion = $store->increment($versionKey);

                if ($newVersion === false) {
                    $current = (int) ($store->get($versionKey) ?? 1);
                    $newVersion = $current + 1;
                    $store->forever($versionKey, $newVersion);
                } elseif ($newVersion <= 1) {
                    $newVersion = 2;
                    $store->forever($versionKey, 2);
                }

                $this->localScopeVersions[$versionKey] = (int) $newVersion;
            } catch (Throwable $e) {
                $this->logRedisFailure($e, 'invalidate', [
                    'scope' => $scope,
                    'tenant_scope' => $resolvedTenant,
                ]);
            }
        }
    }

    /**
     * Retrieve the current version integer for a scope.
     * Version keys are stored forever in Redis without expiration.
     */
    public function getScopeVersion(string $scope, int|string|null $tenantScope = null): int
    {
        $resolvedTenant = $this->resolveTenantScope($tenantScope);
        $versionKey = $this->versionKey($scope, $resolvedTenant);

        if (isset($this->localScopeVersions[$versionKey])) {
            return $this->localScopeVersions[$versionKey];
        }

        try {
            $store = Cache::store($this->store);
            $val = $store->get($versionKey);

            if ($val === null) {
                $store->forever($versionKey, 1);
                $version = 1;
            } else {
                $version = (int) $val;
            }

            $this->localScopeVersions[$versionKey] = $version;

            return $version;
        } catch (Throwable $e) {
            $this->logRedisFailure($e, 'getScopeVersion', [
                'scope' => $scope,
                'tenant_scope' => $resolvedTenant,
            ]);

            return 1;
        }
    }

    /**
     * Deterministically builds a versioned cache key incorporating multiple dependency scopes.
     * Format: purchaser:v1:{tenant_scope}:{user_scope}:{business_date}:{grade}:{dataset}:{filters_hash}:{compound_versions}
     *
     * @param  string|array<int, string>  $scopes
     * @param  array<string, mixed>|string|null  $filters
     */
    public function buildKey(
        string|array $scopes,
        string $dataset,
        int|string|null $tenantScope = null,
        ?int $userId = null,
        Carbon|string|null $businessDate = null,
        ?string $grade = null,
        array|string|null $filters = null,
    ): string {
        $resolvedTenant = $this->resolveTenantScope($tenantScope);
        $userSegment = $userId !== null ? "user_{$userId}" : 'all';
        $dateSegment = $businessDate instanceof Carbon ? $businessDate->format('Y-m-d') : ($businessDate ?: 'all');
        $gradeSegment = $grade ? strtoupper($grade) : 'all';
        $filterSegment = $this->canonicalizeAndHashFilters($filters);
        $versionSegment = $this->buildCompoundVersionSegment($scopes, $resolvedTenant);

        return sprintf(
            '%s:%s:%s:%s:%s:%s:%s:%s',
            self::PREFIX,
            $resolvedTenant,
            $userSegment,
            $dateSegment,
            $gradeSegment,
            $dataset,
            $filterSegment,
            $versionSegment,
        );
    }

    /**
     * Resolves the compound version string for all dependency scopes (e.g. "carts_2:orders_5:prices_1").
     *
     * @param  string|array<int, string>  $scopes
     */
    public function buildCompoundVersionSegment(string|array $scopes, string $resolvedTenant): string
    {
        $scopeList = is_array($scopes) ? array_values(array_unique($scopes)) : [$scopes];
        sort($scopeList, SORT_STRING);

        $parts = [];
        foreach ($scopeList as $scope) {
            $version = $this->getScopeVersion($scope, $resolvedTenant);
            $parts[] = "{$scope}_{$version}";
        }

        return implode(':', $parts);
    }

    /**
     * Canonicalizes nested filter structures recursively and computes a 32-char SHA-256 hash.
     *
     * @param  array<string, mixed>|string|null  $filters
     */
    public function canonicalizeAndHashFilters(array|string|null $filters): string
    {
        if ($filters === null || $filters === [] || $filters === '') {
            return 'nofilter';
        }

        if (is_string($filters)) {
            return substr(hash('sha256', trim($filters)), 0, 32);
        }

        $canonical = $this->canonicalizeValue($filters);

        return substr(hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 32);
    }

    /**
     * Recursively normalizes arrays, scalars, dates, booleans, and enums.
     */
    public function canonicalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                ksort($value, SORT_STRING);
                $normalized = [];
                foreach ($value as $k => $v) {
                    $normalized[(string) $k] = $this->canonicalizeValue($v);
                }

                return $normalized;
            }

            return array_map(fn ($item) => $this->canonicalizeValue($item), $value);
        }

        return $value;
    }

    public function resolveTenantScope(int|string|null $tenantScope): string
    {
        if ($tenantScope === null || $tenantScope === '') {
            return self::DEFAULT_TENANT_SCOPE;
        }

        if (is_int($tenantScope)) {
            return "tenant_{$tenantScope}";
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($tenantScope));

        return $sanitized ?: self::DEFAULT_TENANT_SCOPE;
    }

    /**
     * Check whether Redis connection is operational without throwing.
     */
    public function isRedisAvailable(): bool
    {
        try {
            $testKey = self::PREFIX.':ping_'.microtime(true);
            $store = Cache::store($this->store);
            $store->put($testKey, '1', 5);
            $val = $store->get($testKey);
            $store->forget($testKey);

            return $val === '1';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Reset in-memory local state (instance-scoped, non-static).
     */
    public function resetLocalState(): void
    {
        $this->localScopeVersions = [];
        $this->lastFailureLogAt = 0.0;
    }

    private function versionKey(string $scope, string $resolvedTenant): string
    {
        return sprintf('%s:ver:%s:%s', self::PREFIX, $resolvedTenant, $scope);
    }

    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Rate-limited warning logger for Redis unavailability.
     *
     * @param  array<string, mixed>  $context
     */
    private function logRedisFailure(Throwable $e, string $operation, array $context = []): void
    {
        $now = microtime(true);
        if (($now - $this->lastFailureLogAt) >= self::FAILURE_LOG_INTERVAL_SECONDS) {
            $this->lastFailureLogAt = $now;
            Log::warning('purchaser.read_cache.redis_failure', [
                'operation' => $operation,
                'store' => $this->store,
                'error' => $e->getMessage(),
                'context' => $context,
            ]);
        }
    }
}
