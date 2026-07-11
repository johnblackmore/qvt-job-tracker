<?php

namespace App\Livewire\SampleQuotes;

use App\Models\SampleQuote;
use Livewire\Component;
use Livewire\WithPagination;

class SampleQuoteList extends Component
{
    use WithPagination;

    public string $search = '';

    public function toggleActive(int $id): void
    {
        $quote = SampleQuote::find($id);
        if ($quote) {
            $quote->update(['is_active' => ! $quote->is_active]);
        }
    }

    public function delete(int $id): void
    {
        SampleQuote::find($id)?->delete();
    }

    public function render()
    {
        $sampleQuotes = SampleQuote::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', "%{$this->search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.sample-quotes.sample-quote-list', compact('sampleQuotes'));
    }
}
