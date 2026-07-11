<?php

namespace App\Livewire\Quotes;

use App\Models\EmailTemplate;
use App\Models\Quote;
use App\Services\QuoteEmailService;
use Livewire\Component;

class QuoteShow extends Component
{
    public Quote $quote;

    public bool $showSendModal = false;

    public ?int $selectedTemplateId = null;

    public string $customMessage = '';

    public string $sendStatus = '';

    public function mount(int $id): void
    {
        $this->quote = Quote::with(['customer', 'lineItems.product', 'staff'])->findOrFail($id);
    }

    public function openSendModal(): void
    {
        $this->showSendModal = true;
        $this->sendStatus = '';
        $this->customMessage = '';
    }

    public function closeSendModal(): void
    {
        $this->showSendModal = false;
    }

    public function sendQuote(): void
    {
        if (! $this->quote->customer?->email) {
            $this->sendStatus = 'error: Customer has no email address.';

            return;
        }

        $template = $this->selectedTemplateId
            ? EmailTemplate::find($this->selectedTemplateId)
            : null;

        try {
            $service = new QuoteEmailService;
            $service->sendQuote($this->quote, $template, $this->customMessage ?: null);

            $this->quote->refresh();
            $this->sendStatus = 'sent';
            $this->showSendModal = false;
        } catch (\Exception $e) {
            $this->sendStatus = 'error: '.$e->getMessage();
        }
    }

    public function convertToOrder(): void
    {
        $this->redirect(route('orders.create-from-quote', $this->quote->id), navigate: true);
    }

    public function render()
    {
        $templates = EmailTemplate::where('is_active', true)->orderBy('name')->get();

        return view('livewire.quotes.quote-show', compact('templates'));
    }
}
