<?php

namespace App\Livewire\AiAssistants;

use App\Models\AiDraftGeneration;
use App\Models\AiModelConfig;
use Livewire\Component;
use Livewire\WithPagination;

class EnquiryDraftDetail extends Component
{
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $status = '';

    public string $search = '';

    public ?int $viewingDraftId = null;

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

    public function viewDraft(int $id): void
    {
        $this->viewingDraftId = $id;
        $this->showPrompt = false;
    }

    public function closeDraft(): void
    {
        $this->viewingDraftId = null;
        $this->showPrompt = false;
    }

    public function render()
    {
        $draftsQuery = AiDraftGeneration::with('user', 'enquiry');

        if ($this->search) {
            $draftsQuery->where(function ($q) {
                $q->where('draft_subject', 'like', "%{$this->search}%")
                    ->orWhere('summary', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->dateFrom) {
            $draftsQuery->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $draftsQuery->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->status) {
            $draftsQuery->where('status', $this->status);
        }

        $drafts = $draftsQuery
            ->orderByDesc('created_at')
            ->paginate(20);

        $totalDrafts = AiDraftGeneration::count();
        $linkedDrafts = AiDraftGeneration::whereNotNull('enquiry_id')->count();
        $totalTokens = AiDraftGeneration::sum('input_tokens') + AiDraftGeneration::sum('output_tokens');

        $providerModelStats = AiDraftGeneration::selectRaw('
                provider,
                model,
                count(*) as drafts,
                coalesce(sum(input_tokens), 0) + coalesce(sum(output_tokens), 0) as total_tokens
            ')
            ->whereNotNull('provider')
            ->groupBy('provider', 'model')
            ->get()
            ->map(function ($stat) {
                $config = AiModelConfig::where('provider', $stat->provider)->where('model', $stat->model)->first();
                $cost = null;
                if ($config && $config->input_price !== null && $config->output_price !== null) {
                    $inputTokens = AiDraftGeneration::where('provider', $stat->provider)->where('model', $stat->model)->sum('input_tokens');
                    $outputTokens = AiDraftGeneration::where('provider', $stat->provider)->where('model', $stat->model)->sum('output_tokens');
                    $cost = ($inputTokens / 1_000_000 * $config->input_price) + ($outputTokens / 1_000_000 * $config->output_price);
                }

                return [
                    'provider' => $stat->provider,
                    'model' => $stat->model,
                    'drafts' => $stat->drafts,
                    'total_tokens' => $stat->total_tokens,
                    'cost' => $cost,
                ];
            })
            ->sortByDesc('total_tokens');

        $viewingDraft = null;
        if ($this->viewingDraftId) {
            $viewingDraft = AiDraftGeneration::with('user', 'enquiry.customer')->find($this->viewingDraftId);
        }

        return view('livewire.ai-assistants.enquiry-draft-detail', compact(
            'drafts',
            'totalDrafts', 'linkedDrafts', 'totalTokens',
            'providerModelStats',
            'viewingDraft',
        ));
    }
}
