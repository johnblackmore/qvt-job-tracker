<?php

namespace App\Livewire\Expenses;

use App\Models\AiExtraction;
use Livewire\Component;
use Livewire\WithFileUploads;

class AiExtractionPanel extends Component
{
    use WithFileUploads;

    public $upload;

    public bool $isProcessing = false;

    public ?AiExtraction $extraction = null;

    public array $extractedData = [];

    public string $supplierName = '';

    public string $invoiceNumber = '';

    public string $invoiceDate = '';

    public string $dueDate = '';

    public string $subtotal = '0';

    public string $vatTotal = '0';

    public string $totalAmount = '0';

    public array $lineItems = [];

    public string $documentableType = '';

    public int $documentableId = 0;

    protected function getListeners(): array
    {
        return [
            'set-document-context' => 'setContext',
        ];
    }

    public function setContext(string $type, int $id): void
    {
        $this->documentableType = $type;
        $this->documentableId = $id;
    }

    public function extract(): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $this->isProcessing = true;
        $this->extraction = null;

        $path = $this->upload->store('expenses/ai-extractions', 'local');

        $this->dispatch('extraction-started');

        // The actual AI extraction would be handled by the MCP tool
        // or a dedicated job. For now, we capture the file and dispatch.
        try {
            $extraction = AiExtraction::create([
                'user_id' => auth()->id(),
                'assistant_name' => 'expenses-extractor',
                'provider' => config('ai.assistants.expenses-extractor.provider', 'openai'),
                'model' => config('ai.assistants.expenses-extractor.model', 'gpt-4o'),
                'source_url' => $path,
                'status' => 'processing',
                'extracted_data' => null,
                'raw_response' => null,
            ]);

            $this->extraction = $extraction;

            $this->dispatch('extraction-completed', extractionId: $extraction->id);
        } catch (\Exception $e) {
            $this->addError('upload', 'Failed to process file: '.$e->getMessage());
        }

        $this->isProcessing = false;
    }

    public function applyToForm(): void
    {
        if (! $this->extraction?->extracted_data) {
            return;
        }

        $data = $this->extraction->extracted_data;

        $this->dispatch('apply-extracted-data', data: $data);
    }

    public function render()
    {
        return view('livewire.expenses.ai-extraction-panel');
    }
}
