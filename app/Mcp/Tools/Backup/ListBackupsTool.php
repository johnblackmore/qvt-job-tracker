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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all available backups with metadata. Returns filename, size, disk, type (manual/scheduled), and creation date.')]
class ListBackupsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'disk' => $schema->string()
                ->description('Filter by disk name. If omitted, checks all configured disks.')
                ->nullable(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string(),
            'message' => $schema->string(),
            'backups' => $schema->array(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'disk' => 'nullable|string',
        ]);

        $backups = [];
        $backupName = config('backup.backup.name');
        $disks = $validated['disk']
            ? [$validated['disk']]
            : config('backup.backup.destination.disks');
        $manualFilenames = ManualBackup::pluck('filename')->toArray();

        foreach ($disks as $disk) {
            try {
                $files = Storage::disk($disk)->allFiles($backupName);
            } catch (\Exception) {
                continue;
            }

            foreach ($files as $file) {
                if (! str_ends_with($file, '.zip')) {
                    continue;
                }

                $backups[] = [
                    'filename' => basename($file),
                    'disk' => $disk,
                    'size_bytes' => Storage::disk($disk)->size($file),
                    'type' => in_array(basename($file), $manualFilenames) ? 'manual' : 'scheduled',
                    'created_at' => date('c', Storage::disk($disk)->lastModified($file)),
                ];
            }
        }

        usort($backups, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']));

        return Response::structured([
            'status' => 'completed',
            'message' => 'Found '.count($backups).' backup(s).',
            'backups' => $backups,
        ]);
    }
}
