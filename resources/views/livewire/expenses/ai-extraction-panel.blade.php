<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-base font-display font-semibold text-ink">AI Invoice Assistant</h2>
            <p class="text-xs text-slate-500">Upload a PDF or image to extract invoice data automatically</p>
        </div>
        <x-lucide-bot class="w-6 h-6 text-copper" />
    </div>

    @if($isProcessing && $hasPollStarted)
        <div wire:poll.2s="checkStatus">
            <div class="text-center py-8">
                <div class="w-10 h-10 border-2 border-copper border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                <p class="text-sm font-medium text-slate-600">Processing invoice...</p>
                <p class="text-xs text-slate-500 mt-1">This may take a few seconds</p>
            </div>
        </div>
    @elseif(!$extraction)
        <div class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:border-copper/40 transition-colors">
            <x-lucide-upload class="w-8 h-8 text-slate-400 mx-auto mb-3" />
            <p class="text-sm font-medium text-slate-600 mb-1">Upload an invoice or receipt</p>
            <p class="text-xs text-slate-500 mb-4">PDF, JPG or PNG (max 10MB)</p>
            <input wire:model="upload" type="file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-copper file:text-white hover:file:bg-copper-dark file:cursor-pointer" />
            @error('upload') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <div wire:loading.class="aura text-purple-500 bg-purple-100 [--tw-duration:2000ms]" wire:target="extract" class="mt-4 inline-flex">
                <button wire:click="extract" wire:loading.attr="disabled" wire:loading.class="bg-white" class="inline-flex items-center gap-2 rounded-lg border border-purple-300 px-5 py-2.5 text-sm font-medium text-purple-700 hover:bg-purple-50 transition-colors">
                    <x-lucide-bot class="w-4 h-4" />
                    <span wire:loading.remove wire:target="extract">Extract Data</span>
                    <span wire:loading wire:target="extract" class="text-purple-900">Extracting...</span>
                </button>
            </div>
        </div>
    @elseif($extraction && $extraction->extracted_data)
        <div class="space-y-4" wire:poll.2s="checkStatus">
            <div class="flex items-center gap-2 text-sm text-teal-dark">
                <x-lucide-check-circle-2 class="w-4 h-4" />
                <span>Data extracted successfully</span>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs text-slate-500">Supplier</label>
                    <p class="font-medium text-ink">{{ $extraction->extracted_data['supplier_name'] ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500">Invoice Number</label>
                    <p class="font-medium text-ink">{{ $extraction->extracted_data['invoice_number'] ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500">Date</label>
                    <p class="font-medium text-ink">{{ $extraction->extracted_data['invoice_date'] ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-500">Total</label>
                    <p class="font-medium text-ink">£{{ number_format($extraction->extracted_data['total_amount'] ?? 0, 2) }}</p>
                </div>
            </div>
            <button wire:click="applyToForm" class="inline-flex items-center gap-2 rounded-lg bg-copper px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors">
                <x-lucide-file-input class="w-4 h-4" />
                Apply to Form
            </button>
        </div>
    @elseif($extraction && $extraction->status === 'failed')
        <div class="text-center py-6">
            <x-lucide-alert-circle class="w-8 h-8 text-red-500 mx-auto mb-3" />
            <p class="text-sm font-medium text-red-600">Extraction failed</p>
            <p class="text-xs text-slate-500 mt-1">{{ $extraction->error_message ?? 'Please try again or enter the data manually.' }}</p>
            <button wire:click="$set('extraction', null)" class="mt-3 text-sm text-copper hover:underline">Try again</button>
        </div>
    @endif
</div>
