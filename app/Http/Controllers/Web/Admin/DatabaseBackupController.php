<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exceptions\DatabaseRestoreException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreDatabaseRequest;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\DatabaseRestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
        private readonly DatabaseRestoreService $restoreService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $stats = $this->backupService->getDatabaseStats();
        $backups = $this->backupService->getBackupHistory();
        $safetyBackups = $this->backupService->getSafetyBackups();
        $lastRestoreStatus = $this->restoreService->getLastRestoreStatus();
        $defaultRecipient = $request->user()?->email ?? '';
        $isProduction = app()->isProduction();
        $productionRestoreAllowed = (bool) config('backup.restore.allow_production_restore', false);
        $confirmationPhrase = (string) config('backup.restore.confirmation_phrase', 'RESTORE DATABASE');

        return view('admin.backup.index', compact(
            'stats',
            'backups',
            'safetyBackups',
            'lastRestoreStatus',
            'defaultRecipient',
            'isProduction',
            'productionRestoreAllowed',
            'confirmationPhrase'
        ));
    }

    public function sendEmail(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $result = $this->backupService->sendBackupToEmail(
                recipientEmail: $validated['email'],
                triggeredBy: $request->user(),
            );

            return back()->with('success', "Database backup '{$result['filename']}' successfully generated and emailed to {$validated['email']}.");
        } catch (Throwable $e) {
            return back()->with('error', 'Failed to generate and email database backup: '.$e->getMessage());
        }
    }

    public function download(Request $request): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeAccess();

        $filename = $request->query('file');

        try {
            if ($filename) {
                $safeFilename = basename((string) $filename);
                $filePath = storage_path("app/backups/{$safeFilename}");

                if (! file_exists($filePath)) {
                    return back()->with('error', 'Requested backup file does not exist.');
                }

                $contentType = $this->determineContentType($safeFilename);

                return response()->download($filePath, $safeFilename, [
                    'Content-Type' => $contentType,
                ]);
            }

            $backup = $this->backupService->createBackup();

            return response()->download($backup['path'], $backup['filename'], [
                'Content-Type' => 'application/gzip',
            ]);
        } catch (Throwable $e) {
            return back()->with('error', 'Failed to create backup download: '.$e->getMessage());
        }
    }

    public function downloadSafety(string $filename): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeAccess();

        $safeFilename = basename($filename);
        $filePath = storage_path("app/backups/safety/{$safeFilename}");

        if (! file_exists($filePath)) {
            return back()->with('error', 'Requested safety backup file does not exist.');
        }

        $contentType = $this->determineContentType($safeFilename);

        return response()->download($filePath, $safeFilename, [
            'Content-Type' => $contentType,
        ]);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->authorizeAccess();

        $deleted = $this->backupService->deleteBackup($filename);

        if ($deleted) {
            return back()->with('success', "Backup file '{$filename}' deleted successfully.");
        }

        return back()->with('error', "Could not find backup file '{$filename}' to delete.");
    }

    public function destroySafety(string $filename): RedirectResponse
    {
        $this->authorizeAccess();

        $deleted = $this->backupService->deleteSafetyBackup($filename);

        if ($deleted) {
            return back()->with('success', "Safety backup file '{$filename}' deleted successfully.");
        }

        return back()->with('error', "Could not find safety backup file '{$filename}' to delete.");
    }

    /**
     * Preview backup metadata for confirmation modal.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $request->validate([
            'backup_file' => ['nullable', 'string', 'max:255', 'required_without:upload_file'],
            'upload_file' => ['nullable', 'file', 'max:'.config('backup.restore.max_upload_size_kb', 512000), 'required_without:backup_file'],
        ]);

        $tempPath = null;

        try {
            if ($request->hasFile('upload_file')) {
                $file = $request->file('upload_file');
                $tempDir = storage_path('app/backup-temp');
                if (! File::isDirectory($tempDir)) {
                    File::makeDirectory($tempDir, 0755, true);
                }
                $tempPath = $tempDir.'/prev_'.Str::random(16).'_'.basename($file->getClientOriginalName());
                $file->move($tempDir, basename($tempPath));
                $path = $tempPath;
                $originalName = $file->getClientOriginalName();
            } else {
                $safeFilename = basename((string) $request->input('backup_file'));
                $path = storage_path("app/backups/{$safeFilename}");
                $originalName = $safeFilename;
            }

            $preview = $this->restoreService->inspectBackup($path, $originalName);

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Restore and replace the database from existing backup or uploaded archive.
     */
    public function restore(RestoreDatabaseRequest $request): RedirectResponse
    {
        $this->authorizeAccess();

        $tempPath = null;

        try {
            if ($request->hasFile('upload_file')) {
                $file = $request->file('upload_file');
                $tempDir = storage_path('app/backup-temp');
                if (! File::isDirectory($tempDir)) {
                    File::makeDirectory($tempDir, 0755, true);
                }
                $originalName = $file->getClientOriginalName();
                $tempPath = $tempDir.'/upload_'.Str::random(16).'_'.basename($originalName);
                $file->move($tempDir, basename($tempPath));
                $path = $tempPath;
            } else {
                $safeFilename = basename((string) $request->input('backup_file'));
                $path = storage_path("app/backups/{$safeFilename}");
                $originalName = $safeFilename;

                if (! file_exists($path)) {
                    return back()->with('error', "Backup file '{$safeFilename}' was not found on the server.");
                }
            }

            $result = $this->restoreService->restore(
                sourcePath: $path,
                confirmationPhrase: (string) $request->input('confirmation'),
                operator: $request->user(),
                originalFilename: $originalName
            );

            return back()->with(
                'success',
                "Database successfully replaced from '{$result['source_filename']}'. Safety backup created: '{$result['safety_backup_filename']}'."
            );
        } catch (DatabaseRestoreException $e) {
            $msg = $e->getMessage();
            if ($e->safetyBackupPath) {
                return back()->with('error', $msg)->with('safety_backup', basename($e->safetyBackupPath));
            }

            return back()->with('error', $msg);
        } catch (Throwable $e) {
            return back()->with('error', 'Database restoration failed: '.$e->getMessage());
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function determineContentType(string $filename): string
    {
        $lower = strtolower($filename);
        if (str_ends_with($lower, '.zip')) {
            return 'application/zip';
        }
        if (str_ends_with($lower, '.sql.gz') || str_ends_with($lower, '.gz')) {
            return 'application/gzip';
        }

        return 'application/sql';
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('admin') || $user->can('admin.user.view') || $user->can('admin.settings.view'))) {
            return;
        }

        Gate::authorize('admin.user.view');
    }
}
