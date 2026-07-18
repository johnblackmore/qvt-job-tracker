<?php

namespace App\Mcp\Tools\Backup;

use App\Jobs\CreateBackupJob;
use App\Jobs\RestoreBackupJob;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Restore the database from a previous backup. Optionally creates a backup of the current data first (recommended). All current data will be replaced. Requires confirmation.')]
class RestoreBackupTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()
                ->description('The backup filename to restore (e.g. "2026-07-18-02-30-00.zip").')
                ->required(),
            'disk' => $schema->string()
                ->description('The disk where the backup is stored. Defaults to the first configured backup disk.')
                ->nullable(),
            'create_backup_first' => $schema->boolean()
                ->description('Create a backup of the current data before restoring. Strongly recommended.')
                ->default(true),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without executing.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute the action after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $array): array
    {
        return [
            'status' => $array->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $array->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $array->string()->description('Link to view backups in the staff admin area')->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'filename' => ['required', 'string'],
            'disk' => ['nullable', 'string'],
            'create_backup_first' => ['boolean'],
            'preview' => ['boolean'],
            'confirmed' => ['boolean'],
        ]);

        $isPreview = $validated['preview'] ?? true;
        $isConfirmed = $validated['confirmed'] ?? false;

        if (! $isPreview && ! $isConfirmed) {
            return Response::error(
                'This action requires confirmation. Set preview=true to review what will happen, or confirmed=true to proceed.'
            );
        }

        $disk = $validated['disk'] ?? config('backup.backup.destination.disks.0', 'local');
        $backupName = config('backup.backup.name');
        $exists = Storage::disk($disk)->exists($backupName.'/'.$validated['filename']);

        if (! $exists) {
            return Response::error("Backup file '{$validated['filename']}' not found on disk '{$disk}'. Use the ListBackups tool to see available backups.");
        }

        $size = Storage::disk($disk)->size($backupName.'/'.$validated['filename']);
        $createBackup = $validated['create_backup_first'] ?? true;

        if ($isPreview && ! $isConfirmed) {
            $msg = 'This action will restore the database from backup "'.$validated['filename'].'" ('.round($size / 1024 / 1024, 2).' MB). ';
            $msg .= 'ALL current data will be replaced with the data from this backup. ';
            $msg .= $createBackup ? 'A backup of the current data will be created first. ' : 'No pre-restore backup will be created. ';
            $msg .= 'The application will be unavailable until the restore completes. Are you sure?';

            return Response::structured([
                'status' => 'preview',
                'message' => $msg,
                'data' => [
                    'filename' => $validated['filename'],
                    'disk' => $disk,
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'create_backup_first' => $createBackup,
                ],
            ]);
        }

        if ($createBackup) {
            dispatch(new CreateBackupJob(
                userId: $request->user()->id,
                notes: 'Pre-restore backup',
            ));
        }

        dispatch(new RestoreBackupJob(
            filename: $validated['filename'],
            disk: $disk,
        ));

        return Response::structured([
            'status' => 'completed',
            'message' => 'Restore initiated from backup "'.$validated['filename'].'". '.($createBackup ? 'A pre-restore backup was created first. ' : '').'The application will be unavailable until the restore process completes.',
            'url' => route('admin.backups.index'),
        ]);
    }
}
