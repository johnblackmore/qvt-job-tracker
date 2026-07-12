<?php

namespace App\Livewire\Quotes;

use App\Models\Quote;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class QuoteList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function delete(int $id): void
    {
        Quote::find($id)?->delete();
        $this->dispatch('notify', message: 'Quote deleted.', type: 'success');
    }

    public function convertToOrder(int $id): void
    {
        $quote = Quote::findOrFail($id);
        $this->redirect(route('orders.create-from-quote', $quote->id), navigate: true);
    }

    public function render()
    {
        $quotes = Quote::query()
            ->with('customer')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', function ($cq) {
                            $cq->where('name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.quotes.quote-list', compact('quotes'));
    }
}
