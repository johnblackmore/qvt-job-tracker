<?php

namespace App\Livewire\Enquiries;

use App\Models\Customer;
use App\Models\Enquiry;
use Livewire\Component;

class EnquiryForm extends Component
{
    public ?Enquiry $enquiry = null;

    public ?int $customer_id = null;

    public string $source = 'web';

    public string $status = 'new';

    public string $subject = '';

    public string $message = '';

    public function mount(?int $enquiryId = null, ?int $customerId = null): void
    {
        if ($enquiryId) {
            $this->enquiry = Enquiry::findOrFail($enquiryId);
            $this->customer_id = $this->enquiry->customer_id;
            $this->source = $this->enquiry->source;
            $this->status = $this->enquiry->status;
            $this->subject = $this->enquiry->subject ?? '';
            $this->message = $this->enquiry->message;
        } elseif ($customerId) {
            $this->customer_id = $customerId;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'source' => ['required', 'in:web,phone,email,referral,other'],
            'status' => ['required', 'in:new,in_progress,responded,closed'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $validated['staff_user_id'] = auth()->id();

        if ($this->enquiry) {
            $this->enquiry->update($validated);
        } else {
            Enquiry::create($validated);
        }

        $this->redirect(route('enquiries.index'), navigate: true);
    }

    public function render()
    {
        $customers = Customer::orderBy('name')->get();

        return view('livewire.enquiries.enquiry-form', compact('customers'));
    }
}
