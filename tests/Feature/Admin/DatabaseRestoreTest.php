<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class DatabaseRestoreTest extends TestCase
{
    private User $admin;

    private User $regularUser;

    private string $testBackupsDir;

    private string $testSafetyDir;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            $this->artisan('migrate', ['--force' => true]);
        }

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'staff']);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@example.com']);
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create(['name' => 'Regular User', 'email' => 'regular@example.com']);
        $this->regularUser->assignRole('staff');

        $this->testBackupsDir = storage_path('app/backups');
        $this->testSafetyDir = storage_path('app/backups/safety');

        if (! File::isDirectory($this->testBackupsDir)) {
            File::makeDirectory($this->testBackupsDir, 0755, true);
        }
        if (! File::isDirectory($this->testSafetyDir)) {
            File::makeDirectory($this->testSafetyDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test lock if present
        @unlink(storage_path('app/backups/.restore.lock'));

        parent::tearDown();
    }

    public function test_unauthorized_users_cannot_access_restore_functionality(): void
    {
        // Guests
        $this->get(route('admin.backup.index'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.backup.preview'), ['backup_file' => 'test.sql'])
            ->assertRedirect(route('login'));

        $this->post(route('admin.backup.restore'), ['confirmation' => 'RESTORE DATABASE'])
            ->assertRedirect(route('login'));

        // Non-admin user
        $this->actingAs($this->regularUser)
            ->get(route('admin.backup.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->regularUser)
            ->post(route('admin.backup.preview'), ['backup_file' => 'test.sql'])
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->post(route('admin.backup.restore'), ['confirmation' => 'RESTORE DATABASE'])
            ->assertForbidden();
    }

    public function test_authorized_admin_can_view_backup_page_with_restore_ui(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.backup.index'))
            ->assertOk()
            ->assertSee('Database Backup')
            ->assertSee('Upload & Restore', false)
            ->assertSee('RESTORE DATABASE')
            ->assertSee('Recent Server Backups');
    }

    public function test_restore_rejects_missing_or_incorrect_confirmation_phrase(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.backup.restore'), [
                'confirmation' => 'WRONG PHRASE',
                'backup_file' => 'nonexistent.sql.gz',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($this->admin)
            ->post(route('admin.backup.restore'), [
                'confirmation' => '',
                'backup_file' => 'nonexistent.sql.gz',
            ])
            ->assertSessionHasErrors('confirmation');
    }

    public function test_preview_rejects_missing_files(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.backup.preview'), [
                'backup_file' => 'nonexistent_file_12345.sql.gz',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_preview_inspects_valid_existing_sqlite_backup(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $backup = $backupService->createBackup();

        $this->actingAs($this->admin)
            ->postJson(route('admin.backup.preview'), [
                'backup_file' => $backup['filename'],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'source_filename' => $backup['filename'],
                    'archive_type' => 'gzip',
                    'confirmation_phrase' => 'RESTORE DATABASE',
                ],
            ]);

        // Clean up
        $backupService->deleteBackup($backup['filename']);
    }

    public function test_corrupted_zip_archive_is_rejected(): void
    {
        $corruptZip = UploadedFile::fake()->createWithContent('corrupted.zip', 'NOT_A_VALID_ZIP_ARCHIVE_DATA');

        $this->actingAs($this->admin)
            ->postJson(route('admin.backup.preview'), [
                'upload_file' => $corruptZip,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_zip_archive_missing_sql_dump_is_rejected(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('some_document.txt', 'This is a text file, not an SQL dump.');
        $zip->close();

        $uploadedZip = new UploadedFile($zipPath, 'no_sql.zip', 'application/zip', null, true);

        $this->actingAs($this->admin)
            ->postJson(route('admin.backup.preview'), [
                'upload_file' => $uploadedZip,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        @unlink($zipPath);
    }

    public function test_zip_slip_malicious_archive_is_rejected(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_slip_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../../../evil.sql', 'CREATE TABLE evil (id INT);');
        $zip->close();

        $uploadedZip = new UploadedFile($zipPath, 'malicious.zip', 'application/zip', null, true);

        $this->actingAs($this->admin)
            ->postJson(route('admin.backup.preview'), [
                'upload_file' => $uploadedZip,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        @unlink($zipPath);
    }

    public function test_unsupported_postgres_dump_is_rejected(): void
    {
        $postgresDump = "-- PostgreSQL database dump\nSET standard_conforming_strings = on;\nCREATE TABLE test (id serial primary key);\n";
        $uploadedSql = UploadedFile::fake()->createWithContent('postgres_backup.sql', $postgresDump);

        $this->actingAs($this->admin)
            ->postJson(route('admin.backup.preview'), [
                'upload_file' => $uploadedSql,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_spatie_compatible_zip_upload_restores_cleanly(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $baseBackup = $backupService->createBackup();
        $gz = gzopen($baseBackup['path'], 'rb');
        $baseSql = '';
        while (! gzeof($gz)) {
            $baseSql .= gzread($gz, 64 * 1024);
        }
        gzclose($gz);
        $backupService->deleteBackup($baseBackup['filename']);

        $sqlDumpContent = $baseSql."\nDROP TABLE IF EXISTS `test_spatie_items`;\n".
            "CREATE TABLE `test_spatie_items` (`id` integer primary key autoincrement not null, `name` varchar not null);\n".
            "INSERT INTO `test_spatie_items` (`id`, `name`) VALUES (1, 'Item One'), (2, 'Item Two');\n";

        $zipPath = tempnam(sys_get_temp_dir(), 'spatie_zip_').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('db-dumps/sqlite-database.sql', $sqlDumpContent);
        $zip->close();

        $uploadedZip = new UploadedFile($zipPath, 'greenleaf-erp-backup-2026.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.backup.restore'), [
                'confirmation' => 'RESTORE DATABASE',
                'upload_file' => $uploadedZip,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify the database now contains the restored table and rows
        $this->assertTrue(Schema::hasTable('test_spatie_items'));
        $this->assertEquals(2, DB::table('test_spatie_items')->count());

        // Verify a safety backup was created in storage/app/backups/safety
        $safetyBackups = app(DatabaseBackupService::class)->getSafetyBackups();
        $this->assertNotEmpty($safetyBackups);

        @unlink($zipPath);
    }

    public function test_full_replacement_drops_absent_tables(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $baseBackup = $backupService->createBackup();
        $gz = gzopen($baseBackup['path'], 'rb');
        $baseSql = '';
        while (! gzeof($gz)) {
            $baseSql .= gzread($gz, 64 * 1024);
        }
        gzclose($gz);
        $backupService->deleteBackup($baseBackup['filename']);

        // 1. Create a dummy table that does NOT exist in the backup
        DB::statement('CREATE TABLE `obsolete_legacy_table` (`id` integer primary key, `val` text)');
        DB::table('obsolete_legacy_table')->insert(['id' => 1, 'val' => 'old data']);
        $this->assertTrue(Schema::hasTable('obsolete_legacy_table'));

        // 2. Prepare a backup containing base schema plus `fresh_restored_table` (omitting `obsolete_legacy_table`)
        $freshDump = $baseSql."\nDROP TABLE IF EXISTS `fresh_restored_table`;\n".
            "CREATE TABLE `fresh_restored_table` (`id` integer primary key, `title` text);\n".
            "INSERT INTO `fresh_restored_table` (`id`, `title`) VALUES (10, 'Fresh Title');\n";

        $backupFile = storage_path('app/backups/test_fresh_replacement.sql');
        File::put($backupFile, $freshDump);

        // 3. Restore the backup
        $response = $this->actingAs($this->admin)
            ->post(route('admin.backup.restore'), [
                'confirmation' => 'RESTORE DATABASE',
                'backup_file' => 'test_fresh_replacement.sql',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 4. Verify fresh table exists with expected data
        $this->assertTrue(Schema::hasTable('fresh_restored_table'));
        $this->assertEquals(1, DB::table('fresh_restored_table')->count());

        // 5. CRITICAL: Verify the absent table was removed (full replacement)
        $this->assertFalse(Schema::hasTable('obsolete_legacy_table'));

        @unlink($backupFile);
    }

    public function test_concurrent_restore_is_blocked_by_lock(): void
    {
        $lockFile = storage_path('app/backups/.restore.lock');
        $fp = fopen($lockFile, 'c+');
        flock($fp, LOCK_EX);

        $sqlDumpContent = "CREATE TABLE `test_item` (`id` integer primary key);\n";
        $uploadedSql = UploadedFile::fake()->createWithContent('restore_test.sql', $sqlDumpContent);

        try {
            $response = $this->actingAs($this->admin)
                ->post(route('admin.backup.restore'), [
                    'confirmation' => 'RESTORE DATABASE',
                    'upload_file' => $uploadedSql,
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('error');
            $this->assertStringContainsString('Another database restore operation is currently in progress', session('error'));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lockFile);
        }
    }

    public function test_safety_backup_download_and_deletion(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $safety = $backupService->createSafetyBackup();

        // Download safety backup
        $this->actingAs($this->admin)
            ->get(route('admin.backup.safety.download', $safety['filename']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$safety['filename']);

        // Delete safety backup
        $this->actingAs($this->admin)
            ->delete(route('admin.backup.safety.delete', $safety['filename']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileDoesNotExist($safety['path']);
    }

    public function test_original_backup_download_and_delete_remain_working(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $backup = $backupService->createBackup();

        // Download
        $this->actingAs($this->admin)
            ->get(route('admin.backup.download', ['file' => $backup['filename']]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$backup['filename']);

        // Delete
        $this->actingAs($this->admin)
            ->delete(route('admin.backup.delete', $backup['filename']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFileDoesNotExist($backup['path']);
    }
}
