<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\DatabaseBackupMail;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::findOrCreate('admin', 'web');
        Permission::findOrCreate('admin.user.view', 'web');

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
            'registration_status' => 'approved',
        ]);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo('admin.user.view');

        $this->regularUser = User::factory()->create([
            'email' => 'regular@example.com',
            'registration_status' => 'approved',
        ]);
    }

    public function test_guest_cannot_access_backup_page(): void
    {
        $response = $this->get(route('admin.backup.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_without_permission_cannot_access_backup_page(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.backup.index'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_admin_can_view_backup_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.index'));

        $response->assertOk();
        $response->assertSee('Database Backup');
        $response->assertSee('Email Database Backup');
        $response->assertSee('Recipient Email Address');
        $response->assertSee('admin@greenleaf.com');
    }

    public function test_admin_can_send_backup_to_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.backup.email'), [
                'email' => 'backup-recipient@example.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(DatabaseBackupMail::class, function (DatabaseBackupMail $mail) {
            $this->assertTrue($mail->hasTo('backup-recipient@example.com'));
            $this->assertNotEmpty($mail->attachments());
            $this->assertFileExists($mail->filePath);

            return true;
        });
    }

    public function test_admin_can_download_database_backup(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.backup.download'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/gzip');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
    }

    public function test_admin_can_delete_backup_file(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $backup = $backupService->createBackup();

        $this->assertFileExists($backup['path']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.backup.delete', $backup['filename']));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertFileDoesNotExist($backup['path']);
    }

    public function test_artisan_backup_email_command_sends_email(): void
    {
        Mail::fake();

        $exitCode = $this->artisan('db:backup-email', [
            '--email' => 'cron-backup@example.com',
        ])->run();

        $this->assertEquals(0, $exitCode);

        Mail::assertSent(DatabaseBackupMail::class, function (DatabaseBackupMail $mail) {
            return $mail->hasTo('cron-backup@example.com');
        });
    }
}
