<?php

namespace App\Mcp\Tools;

use App\Services\NetlifyFormService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Check for new Netlify contact form submissions and sync them into enquiries. Requires confirmation.')]
class SyncNetlifySubmissionsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'preview' => $schema->boolean()
                ->description('Set true to see how many unprocessed submissions exist without syncing.')
                ->default(true),
            'confirmed' => $schema->boolean()
                ->description('Set true to confirm and execute the sync.')
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

        $service = app(NetlifyFormService::class);

        if ($isPreview && ! $isConfirmed) {
            $preview = $service->preview();

            return Response::structured([
                'status' => 'preview',
                'message' => $preview['message'],
                'data' => [
                    'unprocessed' => $preview['unprocessed'],
                ],
            ]);
        }

        $result = $service->sync();

        return Response::structured([
            'status' => 'completed',
            'message' => 'Netlify sync complete. Processed: '.$result['processed'].', Skipped: '.$result['skipped'].', Errors: '.$result['errors'].'.',
        ]);
    }
}
