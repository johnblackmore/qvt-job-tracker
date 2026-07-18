<?php

namespace App\Livewire\AiAssistants;

use App\Models\AiExtraction;
use App\Models\AiModelConfig;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesAssistantDetail extends Component
{
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $status = '';

    public string $search = '';

    public ?int $viewingExtractionId = null;

    public bool $showPrompt = false;

    protected $queryString = [
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'status' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'dateFrom', 'dateTo', 'status']);
    }

    public function viewExtraction(int $id): void
    {
        $this->viewingExtractionId = $id;
        $this->showPrompt = false;
    }

    public function closeExtraction(): void
    {
        $this->viewingExtractionId = null;
        $this->showPrompt = false;
    }

    public function render()
    {
        $extractionsQuery = AiExtraction::with('user')
            ->where('assistant_name', 'expenses-extractor');

        if ($this->search) {
            $extractionsQuery->where(function ($q) {
                $q->where('source_url', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($sq) => $sq->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->dateFrom) {
            $extractionsQuery->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $extractionsQuery->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->status) {
            $extractionsQuery->where('status', $this->status);
        }

        $extractions = $extractionsQuery
            ->orderByDesc('created_at')
            ->paginate(20);

        $totalExtractions = AiExtraction::where('assistant_name', 'expenses-extractor')->count();
        $successCount = AiExtraction::where('assistant_name', 'expenses-extractor')->where('status', 'completed')->count();
        $failedCount = AiExtraction::where('assistant_name', 'expenses-extractor')->where('status', 'failed')->count();
        $totalTokens = AiExtraction::where('assistant_name', 'expenses-extractor')->sum('input_tokens')
            + AiExtraction::where('assistant_name', 'expenses-extractor')->sum('output_tokens');

        $providerModelStats = AiExtraction::selectRaw('
                provider, model,
                count(*) as extractions,
                sum(case when status = "completed" then 1 else 0 end) as successful,
                sum(case when status = "failed" then 1 else 0 end) as failed,
                coalesce(sum(input_tokens), 0) + coalesce(sum(output_tokens), 0) as total_tokens
            ')
            ->where('assistant_name', 'expenses-extractor')
            ->whereNotNull('provider')
            ->groupBy('provider', 'model')
            ->get()
            ->map(function ($stat) {
                $config = AiModelConfig::where('provider', $stat->provider)->where('model', $stat->model)->first();
                $cost = null;
                if ($config && $config->input_price !== null && $config->output_price !== null) {
                    $inputTokens = AiExtraction::where('assistant_name', 'expenses-extractor')
                        ->where('provider', $stat->provider)->where('model', $stat->model)->sum('input_tokens');
                    $outputTokens = AiExtraction::where('assistant_name', 'expenses-extractor')
                        ->where('provider', $stat->provider)->where('model', $stat->model)->sum('output_tokens');
                    $cost = ($inputTokens / 1_000_000 * $config->input_price) + ($outputTokens / 1_000_000 * $config->output_price);
                }

                return [
                    'provider' => $stat->provider,
                    'model' => $stat->model,
                    'extractions' => $stat->extractions,
                    'successful' => $stat->successful,
                    'failed' => $stat->failed,
                    'success_rate' => $stat->extractions > 0 ? round($stat->successful / $stat->extractions * 100) : 0,
                    'total_tokens' => $stat->total_tokens,
                    'cost' => $cost,
                ];
            })
            ->sortByDesc('total_tokens');

        $viewingExtraction = null;
        if ($this->viewingExtractionId) {
            $viewingExtraction = AiExtraction::with('user')->find($this->viewingExtractionId);
        }

        return view('livewire.ai-assistants.expenses-assistant-detail', compact(
            'extractions',
            'totalExtractions', 'successCount', 'failedCount', 'totalTokens',
            'providerModelStats',
            'viewingExtraction',
        ));
    }
}
