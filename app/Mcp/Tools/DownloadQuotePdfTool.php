<?php

namespace App\Mcp\Tools;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Generate a downloadable PDF for a quote. Returns a download URL (no binary content). The PDF is customer-safe (retail prices only).')]
class DownloadQuotePdfTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'quote_id' => $schema->integer()
                ->description('The quote ID to generate a PDF for')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Action status')->required(),
            'message' => $schema->string()->description('Human-readable result message for chat UI')->required(),
            'url' => $schema->string()->description('Direct download URL for the PDF')->required(),
            'quote_id' => $schema->integer(),
            'quote_reference' => $schema->string(),
            'filename' => $schema->string(),
            'size_bytes' => $schema->integer(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->user()?->hasRole('admin') ?? false;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
        ]);

        $quote = Quote::with(['customer', 'lineItems'])->findOrFail($validated['quote_id']);

        $pdf = Pdf::loadView('pdf.quote', compact('quote'));
        $pdf->setPaper('a4');
        $sizeBytes = strlen($pdf->output());
        $filename = sprintf('QVT-Quote-%s.pdf', $quote->reference_number);

        return Response::structured([
            'status' => 'completed',
            'message' => "Quote PDF generated for {$quote->reference_number}. Click the link to download.",
            'url' => route('quotes.pdf.download', $quote),
            'quote_id' => $quote->id,
            'quote_reference' => $quote->reference_number,
            'filename' => $filename,
            'size_bytes' => $sizeBytes,
        ]);
    }
}
