<?php

namespace App\Mcp\Tools\Backup;

use App\Models\ManualBackup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a manual backup from storage. Only manual (ad-hoc) backups can be deleted — scheduled backups are managed by the retention policy. Requires confirmation.')]
class DeleteBackupTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()
                ->description('The backup filename to delete (e.g. "2026-07-18-14-30-00.zip").')
                ->required(),
            'disk' => $schema->string()
                ->description('The disk where the backup is stored. Defaults to the first configured backup disk.')
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

        $manualBackup = ManualBackup::where('filename', $validated['filename'])
            ->where('disk', $disk)
            ->first();

        if (! $manualBackup) {
            return Response::error(
                "Backup '{$validated['filename']}' is not a manual backup or does not exist. Only manual backups can be deleted via this tool."
            );
        }

        if ($isPreview && ! $isConfirmed) {
            return Response::structured([
                'status' => 'preview',
                'message' => "This will permanently delete manual backup '{$validated['filename']}' from disk '{$disk}'. This action cannot be undone. Are you sure?",
                'data' => [
                    'filename' => $validated['filename'],
                    'disk' => $disk,
                ],
            ]);
        }

        Storage::disk($disk)->delete(
            $manualBackup->backup_name.'/'.$manualBackup->filename
        );

        $manualBackup->delete();

        return Response::structured([
            'status' => 'completed',
            'message' => "Manual backup '{$validated['filename']}' has been permanently deleted.",
        ]);
    }
}
