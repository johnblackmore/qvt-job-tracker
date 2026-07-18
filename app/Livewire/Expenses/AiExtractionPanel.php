<?php

namespace App\Livewire\Expenses;

use App\Jobs\ProcessExpenseExtraction;
use App\Models\AiExtraction;
use Livewire\Component;
use Livewire\WithFileUploads;

class AiExtractionPanel extends Component
{
    use WithFileUploads;

    public $upload;

    public bool $isProcessing = false;

    public ?AiExtraction $extraction = null;

    public bool $hasPollStarted = false;

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
        $this->hasPollStarted = false;

        $path = $this->upload->store('expenses/ai-extractions', 'local');

        $this->dispatch('extraction-started');

        try {
            $extraction = AiExtraction::create([
                'user_id' => auth()->id(),
                'assistant_name' => 'expenses-extractor',
                'provider' => 'pending',
                'model' => 'pending',
                'source_url' => $path,
                'status' => 'processing',
                'extracted_data' => null,
                'raw_response' => null,
            ]);

            ProcessExpenseExtraction::dispatch($extraction->id);

            $this->extraction = $extraction;
            $this->hasPollStarted = true;

            $this->dispatch('extraction-dispatched', extractionId: $extraction->id);
        } catch (\Throwable $e) {
            $this->addError('upload', 'Failed to process file: '.$e->getMessage());
            $this->isProcessing = false;
        }
    }

    public function checkStatus(): void
    {
        if (! $this->extraction) {
            return;
        }

        $this->extraction->refresh();

        if ($this->extraction->status === 'completed') {
            $this->isProcessing = false;
            $this->hasPollStarted = false;
            $this->dispatch('extraction-completed', extractionId: $this->extraction->id);
        } elseif ($this->extraction->status === 'failed') {
            $this->isProcessing = false;
            $this->hasPollStarted = false;
        }
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
