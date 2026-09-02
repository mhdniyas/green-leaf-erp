<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     * Enforces fail-closed database safety validation AFTER application configuration
     * is resolved, but BEFORE any test helper traits (RefreshDatabase, DatabaseMigrations, etc.) boot.
     */
    protected function setUpTheTestEnvironment(): void
    {
        Facade::clearResolvedInstances();

        if (! $this->app) {
            $this->refreshApplication();

            ParallelTesting::callSetUpTestCaseCallbacks($this);
        }

        $this->assertTestDatabaseSafety();

        $this->setUpTraits();

        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            $callback();
        }

        if (class_exists(Model::class) && isset($this->app['events'])) {
            Model::setEventDispatcher($this->app['events']);
        }

        $this->setUpHasRun = true;
    }

    /**
     * Fail-closed test database safety assertion.
     * Ensures tests NEVER touch local business database (green_leaf_erp) or production targets,
     * executing STRICTLY before any test traits (RefreshDatabase, DatabaseMigrations, etc.) boot.
     */
    public function assertTestDatabaseSafety(): void
    {
        $forbiddenDatabases = [
            'green_leaf_erp',
            'green_leaf_erp_production',
            'green_leaf_erp_prod',
            'greenleaf_erp',
            'production',
        ];

        $defaultConnName = (string) config('database.default');
        $connections = config('database.connections', []);

        if (! isset($connections[$defaultConnName])) {
            throw new \LogicException("SAFETY BLOCK: Default database connection '{$defaultConnName}' is not defined in config.");
        }

        $defaultConfig = $connections[$defaultConnName];
        $defaultDbName = (string) ($defaultConfig['database'] ?? '');

        if (in_array(strtolower($defaultDbName), $forbiddenDatabases, true)) {
            throw new \LogicException(
                "CRITICAL SAFETY BLOCK: Test connection '{$defaultConnName}' is targeting forbidden database '{$defaultDbName}'. ".
                'Tests are strictly prohibited from executing against the local or production business database.'
            );
        }

        foreach ($connections as $name => $connConfig) {
            $dbName = (string) ($connConfig['database'] ?? '');
            if (in_array(strtolower($dbName), $forbiddenDatabases, true)) {
                throw new \LogicException(
                    "CRITICAL SAFETY BLOCK: Connection '{$name}' is configured for forbidden database '{$dbName}'. ".
                    'All test connections must point to an isolated test target.'
                );
            }
        }

        $allowApprovedNonMemoryTestDb = config('database.allow_approved_test_db', false);

        if (! $allowApprovedNonMemoryTestDb) {
            $isMemorySqlite = ($defaultConfig['driver'] ?? '') === 'sqlite' && $defaultDbName === ':memory:';

            if (! $isMemorySqlite) {
                throw new \LogicException(
                    "SAFETY BLOCK: Test default connection '{$defaultConnName}' (driver: ".($defaultConfig['driver'] ?? 'null').", db: {$defaultDbName}) ".
                    'is not isolated. Standard tests must use SQLite :memory:.'
                );
            }
        }
    }
}
