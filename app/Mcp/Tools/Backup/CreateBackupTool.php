<?php

namespace App\Mcp\Tools\Backup;

use App\Models\ManualBackup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Create an ad-hoc manual backup of the database and uploaded files. The backup will be preserved indefinitely and not cleaned up by the retention policy. Requires confirmation.')]
class CreateBackupTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'notes' => $schema->string()
                ->description('Optional notes describing why this backup is being created.')
                ->nullable(),
            'preview' => $schema->boolean()
                ->description('Set true to preview what will happen without executing.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute the action after preview.')
                ->default(false),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['preview', 'completed', 'error'])->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Link to view backups in the staff admin area')->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
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

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => 'I will create a full manual backup (database + uploaded files). The backup will be preserved indefinitely and will not be affected by the automatic cleanup policy. Is that correct?',
                'data' => [
                    'notes' => $validated['notes'] ?? null,
                ],
            ]);
        }

        $exitCode = Artisan::call('backup:run', [
            '--disable-notifications' => true,
        ]);

        if ($exitCode !== 0) {
            return Response::error('Backup failed: '.Artisan::output());
        }

        $backupName = config('backup.backup.name');
        $disks = config('backup.backup.destination.disks');
        $disk = $disks[0] ?? 'local';

        $files = Storage::disk($disk)->allFiles($backupName);
        $latest = collect($files)
            ->filter(fn (string $f) => str_ends_with($f, '.zip'))
            ->sort()
            ->last();

        if ($latest) {
            ManualBackup::create([
                'filename' => basename($latest),
                'disk' => $disk,
                'backup_name' => $backupName,
                'created_by_user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        return Response::structured([
            'status' => 'completed',
            'message' => 'Manual backup created successfully.',
            'url' => route('admin.backups.index'),
        ]);
    }
}
