<?php

namespace App\Livewire\Banking;

use App\Banking\Jobs\SyncReceiptToMonzo;
use App\Banking\Services\ReconciliationService;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\SupplierOrder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class TransactionShow extends Component
{
    use WithFileUploads;

    public BankTransaction $transaction;

    public string $notes = '';

    public string $expenseCategory = '';

    public string $reconciliationStatus = '';

    public bool $showRawData = false;

    public $upload = null;

    public bool $showMatchPanel = false;

    public string $matchType = '';

    public string $matchSearch = '';

    public array $matchSearchResults = [];

    public ?int $selectedMatchId = null;

    public string $paymentAmount = '0';

    public string $paymentMethod = 'bank_transfer';

    public string $paymentReference = '';

    public string $matchAmount = '0';

    public array $aiSuggestions = [];

    public bool $aiLoading = false;

    public bool $aiLoaded = false;

    protected function rules(): array
    {
        return [
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', 'in:bank_transfer,card,cash,other'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'matchAmount' => ['required', 'numeric', 'min:0.01'],
            'selectedMatchId' => ['required', 'integer'],
        ];
    }

    public function mount(BankTransaction $transaction): void
    {
        $this->transaction = $transaction->load(['receipts', 'reconciliationLink']);
        $this->notes = $transaction->notes ?? '';
        $this->expenseCategory = $transaction->expense_category ?? '';
        $this->reconciliationStatus = $transaction->reconciliation_status;
        $this->paymentAmount = (string) abs($transaction->amount);
        $this->matchAmount = (string) abs($transaction->amount);
    }

    public function saveNotes(): void
    {
        $this->transaction->update([
            'notes' => $this->notes ?: null,
        ]);

        $this->dispatch('notify', message: 'Notes updated.', type: 'success');
    }

    public function saveCategory(): void
    {
        $this->transaction->update([
            'expense_category' => $this->expenseCategory ?: null,
        ]);

        $this->dispatch('notify', message: 'Category updated.', type: 'success');
    }

    public function toggleIgnored(): void
    {
        $newStatus = $this->reconciliationStatus === 'ignored' ? 'unmatched' : 'ignored';

        $this->transaction->update([
            'reconciliation_status' => $newStatus,
            'matched_payment_id' => $newStatus === 'ignored' ? null : $this->transaction->matched_payment_id,
        ]);

        $this->reconciliationStatus = $newStatus;

        $message = $newStatus === 'ignored' ? 'Transaction marked as ignored.' : 'Transaction re-opened.';

        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function uploadReceipt(): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,gif', 'max:10240'],
        ]);

        $file = $this->upload;
        $filename = $file->getClientOriginalName();
        $path = $file->store('receipts/'.$this->transaction->bank_account_id.'/'.$this->transaction->id, 'local');

        $receipt = Receipt::create([
            'bank_transaction_id' => $this->transaction->id,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sync_status' => 'pending',
        ]);

        SyncReceiptToMonzo::dispatch($receipt);

        $this->upload = null;
        $this->transaction->load('receipts');

        $this->dispatch('notify', message: 'Receipt uploaded.', type: 'success');
    }

    public function removeReceipt(int $receiptId): void
    {
        $receipt = Receipt::findOrFail($receiptId);

        if ($receipt->file_path && file_exists(storage_path('app/'.$receipt->file_path))) {
            unlink(storage_path('app/'.$receipt->file_path));
        }

        $receipt->delete();

        $this->transaction->load('receipts');

        $this->dispatch('notify', message: 'Receipt removed.', type: 'success');
    }

    public function openMatchPanel(string $type): void
    {
        $this->showMatchPanel = true;
        $this->matchType = $type;
        $this->matchSearch = '';
        $this->matchSearchResults = [];
        $this->selectedMatchId = null;

        if ($type === 'record_payment') {
            $this->paymentAmount = (string) abs($this->transaction->amount);
            $this->paymentMethod = 'bank_transfer';
            $this->paymentReference = '';
        } else {
            $this->matchAmount = (string) abs($this->transaction->amount);
        }

        $this->searchMatchable();
    }

    public function closeMatchPanel(): void
    {
        $this->showMatchPanel = false;
        $this->matchType = '';
        $this->matchSearch = '';
        $this->matchSearchResults = [];
        $this->selectedMatchId = null;
    }

    public function searchMatchable(): void
    {
        $results = [];

        if ($this->matchType === 'payment') {
            $query = Payment::whereNull('bank_transaction_id')
                ->whereHas('order', function ($q) {
                    $q->whereIn('status', ['deposit_paid', 'scheduled', 'in_progress', 'completed']);
                })
                ->with('order.customer');

            if ($this->matchSearch) {
                $search = $this->matchSearch;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('order', function ($oq) use ($search) {
                        $oq->where('reference_number', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($cq) use ($search) {
                                $cq->where('name', 'like', "%{$search}%");
                            });
                    });
                });
            }

            foreach ($query->latest('paid_at')->limit(20)->get() as $payment) {
                $results[] = [
                    'id' => $payment->id,
                    'label' => $payment->order?->reference_number ?? 'Order #'.$payment->order_id,
                    'detail' => $payment->order?->customer?->name ?? 'Unknown',
                    'amount' => (float) $payment->amount,
                    'date' => $payment->paid_at->format('j M Y'),
                ];
            }
        } elseif ($this->matchType === 'expense') {
            $query = Expense::whereNull('bank_transaction_id')
                ->where('status', 'approved');

            if ($this->matchSearch) {
                $search = $this->matchSearch;
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('merchant_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            foreach ($query->latest('expense_date')->limit(20)->get() as $expense) {
                $results[] = [
                    'id' => $expense->id,
                    'label' => $expense->reference_number,
                    'detail' => $expense->merchant_name ?? $expense->description,
                    'amount' => (float) $expense->total_amount,
                    'date' => $expense->expense_date?->format('j M Y') ?? '',
                ];
            }
        } elseif ($this->matchType === 'supplier_order') {
            $query = SupplierOrder::whereNull('bank_transaction_id')
                ->whereIn('status', ['ordered', 'received', 'partially_received'])
                ->with('supplier');

            if ($this->matchSearch) {
                $search = $this->matchSearch;
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            foreach ($query->latest('order_date')->limit(20)->get() as $order) {
                $results[] = [
                    'id' => $order->id,
                    'label' => $order->reference_number,
                    'detail' => $order->supplier?->name ?? $order->invoice_number ?? '',
                    'amount' => (float) $order->total_amount,
                    'date' => $order->order_date?->format('j M Y') ?? '',
                ];
            }
        } elseif ($this->matchType === 'record_payment') {
            $query = Order::whereIn('status', ['pending', 'deposit_paid', 'scheduled', 'in_progress'])
                ->whereColumn('deposit_paid', '<', 'total_amount')
                ->with('customer');

            if ($this->matchSearch) {
                $search = $this->matchSearch;
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            foreach ($query->latest()->limit(20)->get() as $order) {
                $balanceDue = max(0, (float) $order->total_amount - (float) $order->deposit_paid);
                $results[] = [
                    'id' => $order->id,
                    'label' => $order->reference_number,
                    'detail' => $order->customer?->name ?? 'Unknown',
                    'amount' => $balanceDue,
                    'date' => '',
                ];
            }
        }

        $this->matchSearchResults = $results;
    }

    public function updatedMatchSearch(): void
    {
        $this->searchMatchable();
    }

    public function linkToPayment(): void
    {
        $this->validate([
            'selectedMatchId' => ['required', 'integer', 'exists:payments,id'],
            'matchAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $payment = Payment::findOrFail($this->selectedMatchId);
        $service = app(ReconciliationService::class);
        $service->manualMatch($this->transaction, $payment);

        $this->closeMatchPanel();
        $this->reconciliationStatus = 'matched';
        $this->transaction->load(['matchedPayment', 'reconciliationLink.reconcilable']);

        $this->dispatch('notify', message: 'Transaction linked to payment.', type: 'success');
    }

    public function linkToExpense(): void
    {
        $this->validate([
            'selectedMatchId' => ['required', 'integer', 'exists:expenses,id'],
            'matchAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $expense = Expense::findOrFail($this->selectedMatchId);
        $service = app(ReconciliationService::class);
        $service->matchExpense($this->transaction, $expense, (float) $this->matchAmount, auth()->id());

        $this->closeMatchPanel();
        $this->reconciliationStatus = 'matched';
        $this->transaction->load(['matchedPayment', 'reconciliationLink.reconcilable']);

        $this->dispatch('notify', message: 'Transaction linked to expense.', type: 'success');
    }

    public function linkToSupplierOrder(): void
    {
        $this->validate([
            'selectedMatchId' => ['required', 'integer', 'exists:supplier_orders,id'],
            'matchAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $supplierOrder = SupplierOrder::findOrFail($this->selectedMatchId);
        $service = app(ReconciliationService::class);
        $service->matchExpense($this->transaction, $supplierOrder, (float) $this->matchAmount, auth()->id());

        $this->closeMatchPanel();
        $this->reconciliationStatus = 'matched';
        $this->transaction->load(['matchedPayment', 'reconciliationLink.reconcilable']);

        $this->dispatch('notify', message: 'Transaction linked to supplier order.', type: 'success');
    }

    public function recordPaymentAndLink(): void
    {
        $this->validate([
            'selectedMatchId' => ['required', 'integer', 'exists:orders,id'],
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', 'in:bank_transfer,card,cash,other'],
        ]);

        $order = Order::findOrFail($this->selectedMatchId);
        $newTotalPaid = (float) $order->deposit_paid + (float) $this->paymentAmount;

        DB::transaction(function () use ($order, $newTotalPaid) {
            $payment = $order->payments()->create([
                'amount' => $this->paymentAmount,
                'method' => $this->paymentMethod,
                'reference' => $this->paymentReference ?: null,
                'paid_at' => now(),
                'notes' => 'Recorded from bank transaction #'.$this->transaction->id,
                'recorded_by_user_id' => auth()->id(),
                'bank_transaction_id' => $this->transaction->id,
            ]);

            $order->update(['deposit_paid' => $newTotalPaid]);

            $this->transaction->update([
                'matched_payment_id' => $payment->id,
                'reconciliation_status' => 'matched',
            ]);
        });

        $this->closeMatchPanel();
        $this->reconciliationStatus = 'matched';
        $this->transaction->load(['matchedPayment', 'reconciliationLink.reconcilable']);

        $this->dispatch('notify', message: 'Payment recorded and transaction linked.', type: 'success');
    }

    public function unlinkMatch(): void
    {
        $service = app(ReconciliationService::class);

        if ($this->transaction->matchedPayment) {
            $service->unlinkTransaction($this->transaction);
        } elseif ($this->transaction->reconciliationLink) {
            $service->unlinkExpense($this->transaction);
        }

        $this->reconciliationStatus = 'unmatched';
        $this->transaction->load(['matchedPayment', 'reconciliationLink.reconcilable']);

        $this->dispatch('notify', message: 'Transaction unlinked.', type: 'success');
    }

    public function loadAiSuggestions(): void
    {
        $this->aiLoading = true;
        $this->aiLoaded = false;

        try {
            $service = app(ReconciliationService::class);
            $this->aiSuggestions = $service->suggestMatches($this->transaction);
            $this->aiLoaded = true;
        } catch (\Exception $e) {
            report($e);
            $this->aiSuggestions = [];
            $this->dispatch('notify', message: 'Failed to load suggestions: '.$e->getMessage(), type: 'error');
        } finally {
            $this->aiLoading = false;
        }
    }

    public function acceptAiSuggestion(string $type, int $id): void
    {
        $service = app(ReconciliationService::class);
        $amount = abs($this->transaction->amount);

        if ($type === 'payment') {
            $payment = Payment::findOrFail($id);
            $service->manualMatch($this->transaction, $payment);
        } elseif ($type === 'expense') {
            $expense = Expense::findOrFail($id);
            $service->matchExpense($this->transaction, $expense, $amount, auth()->id());
        } elseif ($type === 'supplier_order') {
            $order = SupplierOrder::findOrFail($id);
            $service->matchExpense($this->transaction, $order, $amount, auth()->id());
        } elseif ($type === 'record_payment') {
            $order = Order::findOrFail($id);
            $newTotalPaid = (float) $order->deposit_paid + $amount;

            DB::transaction(function () use ($order, $amount, $newTotalPaid) {
                $payment = $order->payments()->create([
                    'amount' => $amount,
                    'method' => 'bank_transfer',
                    'reference' => $this->transaction->description,
                    'paid_at' => now(),
                    'notes' => 'AI-suggested match from bank transaction #'.$this->transaction->id,
                    'recorded_by_user_id' => auth()->id(),
                    'bank_transaction_id' => $this->transaction->id,
                ]);

                $order->update(['deposit_paid' => $newTotalPaid]);

                $this->transaction->update([
                    'matched_payment_id' => $payment->id,
                    'reconciliation_status' => 'matched',
                ]);
            });
        }

        $this->aiSuggestions = [];
        $this->aiLoaded = false;
        $this->reconciliationStatus = 'matched';
        $this->transaction->load(['matchedPayment', 'reconciliationLink.reconcilable']);

        $this->dispatch('notify', message: 'AI suggestion accepted.', type: 'success');
    }

    public function render()
    {
        $this->transaction->load(['receipts', 'matchedPayment.order.customer', 'reconciliationLink.reconcilable']);

        return view('livewire.banking.transaction-show');
    }
}
