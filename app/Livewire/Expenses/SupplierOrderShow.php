<?php

namespace App\Livewire\Expenses;

use App\Banking\Services\ReconciliationService;
use App\Models\BankTransaction;
use App\Models\SupplierOrder;
use Livewire\Component;

class SupplierOrderShow extends Component
{
    public SupplierOrder $supplierOrder;

    public ?int $selectedTransactionId = null;

    public string $matchAmount = '0';

    public bool $showLinkModal = false;

    public string $transactionSearch = '';

    public ?int $allocatingLineItemId = null;

    public function mount(int $supplierOrderId): void
    {
        $this->supplierOrder = SupplierOrder::with([
            'supplier',
            'lineItems',
            'lineItems.allocations',
            'documents',
            'bankTransaction',
            'createdBy',
        ])->findOrFail($supplierOrderId);
    }

    public function delete(): void
    {
        $this->supplierOrder->delete();
        $this->redirect(route('expenses.supplier-orders.index'), navigate: true);
    }

    public function openLinkModal(): void
    {
        $this->matchAmount = (string) $this->supplierOrder->total_amount;
        $this->selectedTransactionId = null;
        $this->transactionSearch = '';
        $this->showLinkModal = true;
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
    }

    public function linkTransaction(): void
    {
        $this->validate([
            'selectedTransactionId' => ['required', 'integer', 'exists:bank_transactions,id'],
            'matchAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $txn = BankTransaction::findOrFail($this->selectedTransactionId);
        $service = app(ReconciliationService::class);
        $service->matchExpense($txn, $this->supplierOrder, (float) $this->matchAmount, auth()->id());

        $this->showLinkModal = false;
        $this->supplierOrder->load('bankTransaction');
    }

    public function unlinkTransaction(): void
    {
        if ($this->supplierOrder->bankTransaction) {
            $service = app(ReconciliationService::class);
            $service->unlinkExpense($this->supplierOrder->bankTransaction);
            $this->supplierOrder->load('bankTransaction');
        }
    }

    public function startAllocation(int $lineItemId): void
    {
        $this->allocatingLineItemId = $lineItemId;
        $this->dispatch('allocate', lineItemId: $lineItemId)->to(AllocationPanel::class);
    }

    public function closeAllocation(): void
    {
        $this->allocatingLineItemId = null;
        $this->supplierOrder->load('lineItems.allocations');
    }

    public function render()
    {
        $transactions = collect();
        if ($this->showLinkModal) {
            $transactions = BankTransaction::unmatched()
                ->debits()
                ->when($this->transactionSearch, function ($q) {
                    $q->where(function ($query) {
                        $query->where('description', 'like', "%{$this->transactionSearch}%")
                            ->orWhere('merchant_name', 'like', "%{$this->transactionSearch}%");
                    });
                })
                ->with('bankAccount')
                ->latest('transaction_date')
                ->limit(20)
                ->get();
        }

        return view('livewire.expenses.supplier-order-show', compact('transactions'));
    }
}
