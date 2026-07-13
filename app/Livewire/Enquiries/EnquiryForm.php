<?php

namespace App\Livewire\Enquiries;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use Livewire\Component;

class EnquiryForm extends Component
{
    public ?Enquiry $enquiry = null;

    public ?int $customer_id = null;

    public string $email = '';

    public string $phone = '';

    public string $source = 'web';

    public string $status = 'new';

    public string $subject = '';

    public string $message = '';

    public string $internal_notes = '';

    public function mount(?int $enquiryId = null, ?int $customerId = null): void
    {
        if ($enquiryId) {
            $this->enquiry = Enquiry::findOrFail($enquiryId);
            $this->customer_id = $this->enquiry->customer_id;
            $this->email = $this->enquiry->email ?? '';
            $this->phone = $this->enquiry->phone ?? '';
            $this->source = $this->enquiry->source;
            $this->status = $this->enquiry->status;
            $this->subject = $this->enquiry->subject ?? '';
            $this->message = $this->enquiry->message;
            $this->internal_notes = $this->enquiry->internal_notes ?? '';
        }

        if (! $this->customer_id) {
            $this->customer_id = $customerId ?? (request()->query('customerId') ? (int) request()->query('customerId') : null);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['required', 'in:web,phone,email,referral,other'],
            'status' => ['required', 'in:new,in_progress,responded,closed'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $validated['staff_user_id'] = auth()->id();

        if ($this->enquiry) {
            $this->enquiry->update($validated);

            EnquiryActivityLog::create([
                'enquiry_id' => $this->enquiry->id,
                'staff_user_id' => auth()->id(),
                'action' => 'note_added',
                'description' => 'Enquiry details updated',
            ]);
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
