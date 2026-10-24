<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Exceptions\DatabaseRestoreException;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;
use ZipArchive;

class DatabaseRestoreService
{
    private string $tempBaseDir;

    private string $safetyDir;

    private string $statusFile;

    private string $lockFile;

    public function __construct(
        private readonly DatabaseBackupService $backupService
    ) {
        $this->tempBaseDir = config('backup.restore.temp_dir', storage_path('app/backup-temp'));
        $this->safetyDir = config('backup.restore.safety_backup_dir', storage_path('app/backups/safety'));
        $this->statusFile = storage_path('app/backups/restore_status.json');
        $this->lockFile = storage_path('app/backups/.restore.lock');

        if (! File::isDirectory($this->tempBaseDir)) {
            File::makeDirectory($this->tempBaseDir, 0755, true);
        }
        if (! File::isDirectory($this->safetyDir)) {
            File::makeDirectory($this->safetyDir, 0755, true);
        }
    }

    /**
     * Inspect a backup file and return preview metadata without modifying anything.
     *
     * @return array{
     *     source_filename: string,
     *     source_size: int,
     *     source_size_formatted: string,
     *     archive_type: string,
     *     dump_filename: string,
     *     detected_driver: string,
     *     target_database: string,
     *     target_host: string,
     *     target_environment: string,
     *     current_table_count: int,
     *     is_production: bool,
     *     production_restore_allowed: bool,
     *     confirmation_phrase: string
     * }
     */
    public function inspectBackup(string $path, ?string $originalFilename = null): array
    {
        if (! file_exists($path)) {
            throw new DatabaseRestoreException("Backup file not found at: {$path}");
        }

        $filename = $originalFilename ?? basename($path);
        $fileSize = filesize($path);
        if ($fileSize === 0) {
            throw new DatabaseRestoreException('Backup file is empty (0 bytes).');
        }

        $archiveType = $this->detectArchiveType($path, $filename);
        $previewTempDir = "{$this->tempBaseDir}/inspect_".Str::random(12);
        File::makeDirectory($previewTempDir, 0755, true);

        try {
            $extractedSqlPath = $this->extractSqlDump($path, $archiveType, $previewTempDir, $filename);
            $dumpFilename = basename($extractedSqlPath);
            $detectedDriver = $this->detectAndValidateSqlDump($extractedSqlPath);

            $connectionName = config('database.default');
            $targetConfig = config("database.connections.{$connectionName}", []);
            $targetDatabase = (string) ($targetConfig['database'] ?? 'database');
            $targetHost = (string) ($targetConfig['host'] ?? 'localhost');
            $targetDriver = (string) ($targetConfig['driver'] ?? 'mysql');

            // Validate compatibility with configured database driver
            if ($targetDriver === 'mysql' && $detectedDriver === 'sqlite') {
                throw new DatabaseRestoreException('Incompatible dump: The backup contains an SQLite dump, but the target application database is MySQL.');
            }
            if ($targetDriver === 'sqlite' && $detectedDriver === 'mysql') {
                throw new DatabaseRestoreException('Incompatible dump: The backup contains a MySQL dump, but the target application database is SQLite.');
            }

            $currentStats = $this->backupService->getDatabaseStats($connectionName);
            $isProduction = app()->isProduction();
            $productionAllowed = (bool) config('backup.restore.allow_production_restore', false);

            return [
                'source_filename' => $filename,
                'source_size' => $fileSize,
                'source_size_formatted' => $this->backupService->formatBytes($fileSize),
                'archive_type' => $archiveType,
                'dump_filename' => $dumpFilename,
                'detected_driver' => $detectedDriver,
                'target_database' => $targetDatabase,
                'target_host' => $targetHost,
                'target_environment' => app()->environment(),
                'current_table_count' => count($currentStats['tables'] ?? []),
                'is_production' => $isProduction,
                'production_restore_allowed' => $productionAllowed,
                'confirmation_phrase' => (string) config('backup.restore.confirmation_phrase', 'RESTORE DATABASE'),
            ];
        } finally {
            if (File::isDirectory($previewTempDir)) {
                File::deleteDirectory($previewTempDir);
            }
        }
    }

    /**
     * Restore and completely replace the application database from a backup file.
     *
     * @return array{
     *     status: string,
     *     source_filename: string,
     *     safety_backup_filename: string,
     *     target_database: string,
     *     tables_restored: int,
     *     duration_seconds: float
     * }
     */
    public function restore(
        string $sourcePath,
        string $confirmationPhrase,
        ?User $operator = null,
        ?string $originalFilename = null,
        ?string $connectionName = null
    ): array {
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);

        $expectedPhrase = (string) config('backup.restore.confirmation_phrase', 'RESTORE DATABASE');
        if (trim($confirmationPhrase) !== $expectedPhrase) {
            throw new DatabaseRestoreException("Invalid confirmation phrase. You must type '{$expectedPhrase}' exactly to proceed.");
        }

        if (app()->isProduction() && ! config('backup.restore.allow_production_restore', false)) {
            throw new DatabaseRestoreException('Database restoration is disabled in production. Set BACKUP_ALLOW_PRODUCTION_RESTORE=true in environment configuration to enable.');
        }

        $connectionName ??= config('database.default');
        $targetConfig = config("database.connections.{$connectionName}", []);
        $targetDatabase = (string) ($targetConfig['database'] ?? 'database');
        $filename = $originalFilename ?? basename($sourcePath);

        // Acquire exclusive lock
        $lockHandle = fopen($this->lockFile, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            throw new DatabaseRestoreException('Another database restore operation is currently in progress. Please wait for it to complete.');
        }

        $startTime = microtime(true);
        $restoreWorkDir = "{$this->tempBaseDir}/run_".Str::random(16);
        File::makeDirectory($restoreWorkDir, 0755, true);

        $safetyBackup = null;
        $maintenanceEnabled = false;

        $this->writeStatus([
            'status' => 'validating',
            'started_at' => now()->toDateTimeString(),
            'source_filename' => $filename,
            'target_database' => $targetDatabase,
            'operator' => $operator?->email ?? 'System',
        ]);

        try {
            // 1. Extract and validate SQL dump
            $archiveType = $this->detectArchiveType($sourcePath, $filename);
            $sqlDumpPath = $this->extractSqlDump($sourcePath, $archiveType, $restoreWorkDir, $filename);
            $this->detectAndValidateSqlDump($sqlDumpPath);

            // 2. Enable maintenance mode to isolate incoming requests
            try {
                Artisan::call('down');
                $maintenanceEnabled = true;
            } catch (Throwable $e) {
                Log::warning("Could not enable maintenance mode: {$e->getMessage()}");
            }

            // 3. Create pre-restoration safety backup
            $this->writeStatus([
                'status' => 'creating_safety_backup',
                'source_filename' => $filename,
                'target_database' => $targetDatabase,
            ]);

            $safetyBackup = $this->backupService->createSafetyBackup($connectionName);

            $this->writeStatus([
                'status' => 'safety_backup_verified',
                'safety_backup' => $safetyBackup['filename'],
                'safety_backup_path' => $safetyBackup['path'],
                'source_filename' => $filename,
                'target_database' => $targetDatabase,
            ]);

            // 4. Wipe all existing tables in target database (clean slate replacement)
            $this->writeStatus([
                'status' => 'clearing_existing_tables',
                'source_filename' => $filename,
                'target_database' => $targetDatabase,
            ]);

            $this->dropAllTargetTables($connectionName);

            // 5. Execute restore dump
            $this->writeStatus([
                'status' => 'importing_sql_dump',
                'source_filename' => $filename,
                'target_database' => $targetDatabase,
            ]);

            $this->executeRestoreSql($sqlDumpPath, $connectionName);

            // 6. Post-restore verification
            $this->verifyRestorationIntegrity($connectionName);

            // 7. Clear caches
            try {
                Artisan::call('cache:clear');
                if (class_exists(PermissionRegistrar::class)) {
                    app(PermissionRegistrar::class)->forgetCachedPermissions();
                }
            } catch (Throwable $e) {
                Log::warning("Cache clear after restore failed: {$e->getMessage()}");
            }

            $duration = round(microtime(true) - $startTime, 2);
            $finalStats = $this->backupService->getDatabaseStats($connectionName);
            $tablesCount = count($finalStats['tables'] ?? []);

            $this->writeStatus([
                'status' => 'success',
                'completed_at' => now()->toDateTimeString(),
                'duration_seconds' => $duration,
                'source_filename' => $filename,
                'safety_backup' => $safetyBackup['filename'],
                'target_database' => $targetDatabase,
                'tables_count' => $tablesCount,
                'operator' => $operator?->email ?? 'System',
            ]);

            if (function_exists('activity')) {
                try {
                    activity('database_restore')
                        ->causedBy($operator)
                        ->withProperties([
                            'source_filename' => $filename,
                            'safety_backup' => $safetyBackup['filename'],
                            'target_database' => $targetDatabase,
                            'tables_restored' => $tablesCount,
                            'duration_seconds' => $duration,
                        ])
                        ->log("Database replaced successfully from backup '{$filename}'");
                } catch (Throwable $e) {
                    Log::warning("Failed to record activity log for restore: {$e->getMessage()}");
                }
            }

            return [
                'status' => 'success',
                'source_filename' => $filename,
                'safety_backup_filename' => $safetyBackup['filename'],
                'target_database' => $targetDatabase,
                'tables_restored' => $tablesCount,
                'duration_seconds' => $duration,
            ];
        } catch (Throwable $e) {
            Log::error("Database restore failed: {$e->getMessage()}");

            // Automated recovery: attempt rollback using safety backup if created
            $recovered = false;
            if ($safetyBackup && file_exists($safetyBackup['path'])) {
                $recovered = $this->attemptSafetyRollback($safetyBackup['path'], $connectionName);
            }

            $this->writeStatus([
                'status' => $recovered ? 'failed_and_recovered' : 'critical_recovery_needed',
                'failed_at' => now()->toDateTimeString(),
                'error_message' => $e->getMessage(),
                'source_filename' => $filename,
                'safety_backup' => $safetyBackup['filename'] ?? null,
                'safety_backup_path' => $safetyBackup['path'] ?? null,
                'target_database' => $targetDatabase,
                'operator' => $operator?->email ?? 'System',
            ]);

            if ($recovered) {
                throw new DatabaseRestoreException(
                    "Database restore failed during replacement: {$e->getMessage()}. The database was safely rolled back to its pre-restore state using safety backup '{$safetyBackup['filename']}'.",
                    500,
                    $e,
                    $safetyBackup['path'] ?? null
                );
            }

            $safetyMsg = $safetyBackup ? "Pre-restoration safety backup is preserved at: {$safetyBackup['path']}." : 'No safety backup was generated before failure.';
            throw new DatabaseRestoreException(
                "CRITICAL: Database restoration failed ({$e->getMessage()}). {$safetyMsg} Please contact system administrator to restore manually.",
                500,
                $e,
                $safetyBackup['path'] ?? null
            );
        } finally {
            if ($maintenanceEnabled) {
                try {
                    Artisan::call('up');
                } catch (Throwable) {
                    // Ignore
                }
            }

            if (File::isDirectory($restoreWorkDir)) {
                File::deleteDirectory($restoreWorkDir);
            }

            if ($lockHandle) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                @unlink($this->lockFile);
            }
        }
    }

    /**
     * Retrieve the last recorded restore status.
     *
     * @return array<string, mixed>|null
     */
    public function getLastRestoreStatus(): ?array
    {
        if (! File::exists($this->statusFile)) {
            return null;
        }

        try {
            $contents = File::get($this->statusFile);
            $data = json_decode($contents, true);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Detect archive format (zip, gzip, sql).
     */
    private function detectArchiveType(string $path, string $filename): string
    {
        $lower = strtolower($filename);
        if (str_ends_with($lower, '.zip')) {
            return 'zip';
        }
        if (str_ends_with($lower, '.sql.gz') || str_ends_with($lower, '.gz')) {
            return 'gzip';
        }
        if (str_ends_with($lower, '.sql')) {
            return 'sql';
        }

        // Check magic bytes
        $handle = @fopen($path, 'rb');
        if ($handle) {
            $magic = fread($handle, 4);
            fclose($handle);
            if (str_starts_with($magic, "PK\x03\x04") || str_starts_with($magic, "PK\x05\x06")) {
                return 'zip';
            }
            if (str_starts_with($magic, "\x1f\x8b")) {
                return 'gzip';
            }
        }

        return 'sql';
    }

    /**
     * Safely extract SQL dump from ZIP, GZIP, or plain SQL archive into target temp dir.
     */
    private function extractSqlDump(string $sourcePath, string $archiveType, string $targetTempDir, string $filename): string
    {
        if ($archiveType === 'zip') {
            return $this->extractSqlFromZip($sourcePath, $targetTempDir);
        }

        if ($archiveType === 'gzip') {
            $destSql = "{$targetTempDir}/".Str::replaceLast('.gz', '', basename($filename));
            if (! str_ends_with($destSql, '.sql')) {
                $destSql .= '.sql';
            }
            $this->uncompressGzip($sourcePath, $destSql);

            return $destSql;
        }

        // Plain SQL file
        $destSql = "{$targetTempDir}/".basename($filename);
        if (! copy($sourcePath, $destSql)) {
            throw new DatabaseRestoreException('Failed to copy SQL file to temporary working directory.');
        }

        return $destSql;
    }

    /**
     * Safely extract candidate SQL dump from ZIP archive with ZipSlip prevention.
     */
    private function extractSqlFromZip(string $zipPath, string $targetTempDir): string
    {
        $zip = new ZipArchive;
        $openResult = $zip->open($zipPath);

        if ($openResult !== true) {
            throw new DatabaseRestoreException("Failed to open backup ZIP archive. Error code: {$openResult}");
        }

        // Apply encryption password if configured
        $configuredPassword = config('backup.backup.password') ?: env('BACKUP_ARCHIVE_PASSWORD');
        if (! empty($configuredPassword)) {
            $zip->setPassword($configuredPassword);
        }

        $totalUncompressedBytes = 0;
        $maxAllowedBytes = 1024 * 1024 * 1024; // 1 GB limit
        $dumpEntryIndex = null;
        $dumpEntryName = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! $stat) {
                continue;
            }

            $entryName = $stat['name'];
            $entrySize = $stat['size'];

            // Prevent Zip Slip vulnerability
            if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                $zip->close();
                throw new DatabaseRestoreException("Security violation: Archive contains unsafe path traversal entry: {$entryName}");
            }

            $totalUncompressedBytes += $entrySize;
            if ($totalUncompressedBytes > $maxAllowedBytes) {
                $zip->close();
                throw new DatabaseRestoreException('Archive contents exceed maximum allowed uncompressed size limit (1 GB).');
            }

            $lower = strtolower($entryName);
            if (str_ends_with($lower, '.sql') || str_ends_with($lower, '.sql.gz')) {
                // Prioritize Spatie db-dumps/ directory
                if ($dumpEntryIndex === null || str_contains($lower, 'db-dumps/')) {
                    $dumpEntryIndex = $i;
                    $dumpEntryName = $entryName;
                }
            }
        }

        if ($dumpEntryIndex === null || $dumpEntryName === null) {
            $zip->close();
            throw new DatabaseRestoreException('The backup archive does not contain a supported database dump file (e.g. db-dumps/*.sql or *.sql.gz).');
        }

        $stream = $zip->getStream($dumpEntryName);
        if (! $stream) {
            $zip->close();
            throw new DatabaseRestoreException("Unable to read dump entry '{$dumpEntryName}' from archive. If the archive is password-protected, verify BACKUP_ARCHIVE_PASSWORD.");
        }

        $tempExtracted = "{$targetTempDir}/".basename($dumpEntryName);
        $destFile = fopen($tempExtracted, 'wb');
        if (! $destFile) {
            fclose($stream);
            $zip->close();
            throw new DatabaseRestoreException('Unable to write extracted dump to temporary storage.');
        }

        while (! feof($stream)) {
            $chunk = fread($stream, 64 * 1024);
            if ($chunk !== false) {
                fwrite($destFile, $chunk);
            }
        }

        fclose($stream);
        fclose($destFile);
        $zip->close();

        if (! file_exists($tempExtracted) || filesize($tempExtracted) === 0) {
            throw new DatabaseRestoreException('Extracted database dump file is empty or missing.');
        }

        // If the extracted dump inside the zip was gzip compressed, uncompress it
        if (str_ends_with(strtolower($tempExtracted), '.gz')) {
            $uncompressedSql = "{$targetTempDir}/".Str::replaceLast('.gz', '', basename($tempExtracted));
            if (! str_ends_with($uncompressedSql, '.sql')) {
                $uncompressedSql .= '.sql';
            }
            $this->uncompressGzip($tempExtracted, $uncompressedSql);
            @unlink($tempExtracted);

            return $uncompressedSql;
        }

        return $tempExtracted;
    }

    /**
     * Uncompress a .gz file to destination path.
     */
    private function uncompressGzip(string $gzPath, string $destPath): void
    {
        $gz = @gzopen($gzPath, 'rb');
        if (! $gz) {
            throw new DatabaseRestoreException('Unable to open gzip compressed dump file.');
        }

        $dest = fopen($destPath, 'wb');
        if (! $dest) {
            gzclose($gz);
            throw new DatabaseRestoreException('Unable to create uncompressed SQL working file.');
        }

        while (! gzeof($gz)) {
            $chunk = gzread($gz, 64 * 1024);
            if ($chunk !== false && $chunk !== '') {
                fwrite($dest, $chunk);
            }
        }

        gzclose($gz);
        fclose($dest);

        if (! file_exists($destPath) || filesize($destPath) === 0) {
            throw new DatabaseRestoreException('Uncompressed SQL dump file is empty.');
        }
    }

    /**
     * Inspect SQL dump content, validate database engine, and ensure actionable statements exist.
     */
    private function detectAndValidateSqlDump(string $sqlPath): string
    {
        $handle = fopen($sqlPath, 'r');
        if (! $handle) {
            throw new DatabaseRestoreException('Unable to open SQL dump file for validation.');
        }

        $headerSample = fread($handle, 64 * 1024); // read first 64KB
        fclose($handle);

        if ($headerSample === false || trim($headerSample) === '') {
            throw new DatabaseRestoreException('SQL dump file is empty.');
        }

        // Check for unsupported database signatures
        if (
            str_contains($headerSample, 'PostgreSQL database dump') ||
            str_contains($headerSample, 'SET standard_conforming_strings') ||
            str_contains($headerSample, 'pg_catalog')
        ) {
            throw new DatabaseRestoreException('Unsupported database format: detected PostgreSQL dump. Target database requires MySQL/SQLite compatible SQL.');
        }

        // Detect engine
        $isSqlite = str_contains($headerSample, 'PRAGMA foreign_keys') || str_contains($headerSample, 'sqlite_master');
        $isMysql = str_contains($headerSample, 'MySQL dump') ||
            str_contains($headerSample, 'SET FOREIGN_KEY_CHECKS') ||
            str_contains($headerSample, '/*!40101') ||
            str_contains($headerSample, 'ENGINE=') ||
            str_contains($headerSample, 'Green Leaf ERP Database Backup');

        // Check for actionable table DDL/DML statements
        $hasActionableStatements = (bool) preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE)\b/i', $headerSample);

        if (! $hasActionableStatements) {
            // Read more of file if header didn't contain CREATE TABLE yet (e.g. large comments)
            $handle = fopen($sqlPath, 'r');
            $linesChecked = 0;
            while ($handle && ! feof($handle) && $linesChecked < 500) {
                $line = fgets($handle);
                $linesChecked++;
                if ($line !== false && preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE)\b/i', $line)) {
                    $hasActionableStatements = true;
                    break;
                }
            }
            if ($handle) {
                fclose($handle);
            }
        }

        if (! $hasActionableStatements) {
            throw new DatabaseRestoreException('The backup dump does not contain valid database schema or table data statements.');
        }

        // Sanitize any dangerous database selection / creation statements to prevent cross-database overwriting
        $this->sanitizeSqlDump($sqlPath);

        return $isSqlite ? 'sqlite' : 'mysql';
    }

    /**
     * Sanitize SQL dump to ensure it never changes or creates another application's database.
     */
    private function sanitizeSqlDump(string $sqlPath): void
    {
        $tempSanitized = "{$sqlPath}.sanitized";
        $source = fopen($sqlPath, 'r');
        $dest = fopen($tempSanitized, 'w');

        if (! $source || ! $dest) {
            if ($source) {
                fclose($source);
            }
            if ($dest) {
                fclose($dest);
            }

            return;
        }

        while (! feof($source)) {
            $line = fgets($source);
            if ($line === false) {
                break;
            }

            // Strip any CREATE DATABASE or USE statements so import is strictly locked to configured connection
            if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+[`\'"]?[a-zA-Z0-9_-]+[`\'"]?\s*;)/i', $line)) {
                fwrite($dest, "-- [Green Leaf ERP: Sanitized database directive]\n");

                continue;
            }

            fwrite($dest, $line);
        }

        fclose($source);
        fclose($dest);

        unlink($sqlPath);
        rename($tempSanitized, $sqlPath);
    }

    /**
     * Drop all existing tables and views in target database for full replacement.
     */
    private function dropAllTargetTables(string $connectionName): void
    {
        $connection = DB::connection($connectionName);
        while ($connection->transactionLevel() > 0) {
            $connection->commit();
        }

        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF');
            $views = $connection->select("SELECT name FROM sqlite_master WHERE type = 'view'");
            foreach ($views as $v) {
                $connection->statement("DROP VIEW IF EXISTS `{$v->name}`");
            }
            $tables = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $t) {
                $connection->statement("DROP TABLE IF EXISTS `{$t->name}`");
            }
            $connection->statement('PRAGMA foreign_keys = ON');

            return;
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS = 0');
        $views = $connection->select("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        foreach ($views as $v) {
            $arr = (array) $v;
            $name = (string) reset($arr);
            if ($name !== '') {
                $connection->statement("DROP VIEW IF EXISTS `{$name}`");
            }
        }
        $tables = $connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        foreach ($tables as $t) {
            $arr = (array) $t;
            $name = (string) reset($arr);
            if ($name !== '') {
                $connection->statement("DROP TABLE IF EXISTS `{$name}`");
            }
        }
        $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Execute SQL restore script via CLI client or streaming PDO statements.
     */
    private function executeRestoreSql(string $sqlPath, string $connectionName): void
    {
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $mysqlBin = $this->findMysqlBinary();
            if ($mysqlBin !== null) {
                $this->executeRestoreMysqlCli($mysqlBin, $sqlPath, $connectionName);

                return;
            }
        }

        // Streaming PDO execution fallback (supports SQLite and MySQL without CLI)
        $this->executeRestorePdoStream($sqlPath, $connectionName);
    }

    /**
     * Execute MySQL restore via mysql CLI using secure credentials config file.
     */
    private function executeRestoreMysqlCli(string $mysqlBin, string $sqlPath, string $connectionName): void
    {
        $config = config("database.connections.{$connectionName}");
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');

        // Create secure temporary client options file to avoid exposing password in process list
        $tempCnf = "{$this->tempBaseDir}/cnf_".Str::random(16).'.cnf';
        $cnfContent = "[client]\nhost=\"{$host}\"\nport=\"{$port}\"\nuser=\"{$username}\"\npassword=\"{$password}\"\ndefault-character-set=utf8mb4\n";
        file_put_contents($tempCnf, $cnfContent);
        @chmod($tempCnf, 0600);

        try {
            $command = sprintf(
                '%s --defaults-extra-file=%s --database=%s < %s',
                escapeshellcmd($mysqlBin),
                escapeshellarg($tempCnf),
                escapeshellarg($database),
                escapeshellarg($sqlPath)
            );

            $result = Process::timeout(600)->run($command);

            if (! $result->successful()) {
                throw new DatabaseRestoreException('MySQL restore process failed: '.($result->error() ?: $result->output()));
            }
        } finally {
            @unlink($tempCnf);
        }
    }

    /**
     * Stream SQL statements line by line via PDO.
     */
    private function executeRestorePdoStream(string $sqlPath, string $connectionName): void
    {
        $connection = DB::connection($connectionName);
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF');
        }

        $handle = fopen($sqlPath, 'r');
        if (! $handle) {
            throw new DatabaseRestoreException('Unable to open SQL file for execution.');
        }

        $buffer = '';
        while (! feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            $trimmed = trim($line);
            // Skip comments and empty lines
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            $buffer .= $line;

            if (str_ends_with($trimmed, ';')) {
                try {
                    $connection->unprepared($buffer);
                } catch (Throwable $e) {
                    fclose($handle);
                    throw new DatabaseRestoreException("SQL execution error: {$e->getMessage()}", 500, $e);
                }
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            try {
                $connection->unprepared($buffer);
            } catch (Throwable $e) {
                fclose($handle);
                throw new DatabaseRestoreException("SQL execution error on remaining buffer: {$e->getMessage()}", 500, $e);
            }
        }

        fclose($handle);

        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Verify database connectivity and schema after restore.
     */
    private function verifyRestorationIntegrity(string $connectionName): void
    {
        $dbName = (string) config("database.connections.{$connectionName}.database");
        if ($dbName !== ':memory:') {
            DB::purge($connectionName);
            DB::reconnect($connectionName);
        }

        $connection = DB::connection($connectionName);
        $connection->getPdo();

        $stats = $this->backupService->getDatabaseStats($connectionName);
        $tableCount = count($stats['tables'] ?? []);

        if ($tableCount === 0) {
            throw new DatabaseRestoreException('Restoration verification failed: target database has 0 tables after restore.');
        }
    }

    /**
     * Automated rollback to safety backup upon restoration failure.
     */
    private function attemptSafetyRollback(string $safetyGzPath, string $connectionName): bool
    {
        $rollbackTemp = "{$this->tempBaseDir}/rollback_".Str::random(12);
        File::makeDirectory($rollbackTemp, 0755, true);

        try {
            $sqlPath = "{$rollbackTemp}/safety_rollback.sql";
            $this->uncompressGzip($safetyGzPath, $sqlPath);
            $this->dropAllTargetTables($connectionName);
            $this->executeRestoreSql($sqlPath, $connectionName);
            $this->verifyRestorationIntegrity($connectionName);

            Log::info("Safety rollback succeeded using {$safetyGzPath}");

            return true;
        } catch (Throwable $e) {
            Log::critical("Safety rollback failed: {$e->getMessage()}");

            return false;
        } finally {
            if (File::isDirectory($rollbackTemp)) {
                File::deleteDirectory($rollbackTemp);
            }
        }
    }

    /**
     * Find mysql binary path.
     */
    private function findMysqlBinary(): ?string
    {
        $candidates = ['mysql', '/usr/bin/mysql', '/usr/local/bin/mysql', '/opt/homebrew/bin/mysql'];

        foreach ($candidates as $bin) {
            $result = Process::run("{$bin} --version");
            if ($result->successful()) {
                return $bin;
            }
        }

        return null;
    }

    /**
     * Write status to filesystem outside target database.
     *
     * @param  array<string, mixed>  $data
     */
    private function writeStatus(array $data): void
    {
        try {
            $existing = $this->getLastRestoreStatus() ?? [];
            $merged = array_merge($existing, $data);
            File::put($this->statusFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            Log::warning("Could not write restore status: {$e->getMessage()}");
        }
    }
}
