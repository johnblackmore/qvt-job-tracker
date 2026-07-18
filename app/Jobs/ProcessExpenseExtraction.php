<?php

namespace App\Jobs;

use App\Models\AiExtraction;
use App\Services\Ai\Assistants\ExpensesExtractorAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessExpenseExtraction implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;

    public $tries = 1;

    public function __construct(
        public int $extractionId,
    ) {}

    public function handle(ExpensesExtractorAssistant $assistant): void
    {
        $extraction = AiExtraction::find($this->extractionId);

        if (! $extraction) {
            Log::warning('Expense extraction job: extraction record not found', [
                'extraction_id' => $this->extractionId,
            ]);

            return;
        }

        Log::info('Expense extraction job starting', [
            'extraction_id' => $this->extractionId,
            'source_url' => $extraction->source_url,
            'status' => $extraction->status,
        ]);

        try {
            $assistant->processExtraction($extraction);

            $extraction->refresh();

            Log::info('Expense extraction job completed', [
                'extraction_id' => $this->extractionId,
                'status' => $extraction->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('Expense extraction job failed', [
                'extraction_id' => $this->extractionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $extraction->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Expense extraction job completely failed (worker-level)', [
            'extraction_id' => $this->extractionId,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        AiExtraction::where('id', $this->extractionId)
            ->where('status', 'processing')
            ->update([
                'status' => 'failed',
                'error_message' => 'Worker error: '.$e->getMessage(),
            ]);
    }
}
