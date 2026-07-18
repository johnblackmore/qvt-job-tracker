<?php

namespace App\Jobs;

use App\Models\AiExtraction;
use App\Services\Ai\Assistants\ExpensesExtractorAssistant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessExpenseExtraction implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $extractionId,
    ) {}

    public function handle(ExpensesExtractorAssistant $assistant): void
    {
        $extraction = AiExtraction::find($this->extractionId);

        if (! $extraction) {
            return;
        }

        $assistant->processExtraction($extraction);
    }
}
