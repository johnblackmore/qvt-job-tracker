<?php

namespace App\Livewire\Enquiries;

use App\Models\Enquiry;
use Livewire\Component;
use Livewire\WithPagination;

class EnquiryList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function markResponded(int $id): void
    {
        $enquiry = Enquiry::find($id);
        if ($enquiry) {
            $enquiry->update([
                'status' => 'responded',
                'responded_at' => now(),
                'staff_user_id' => auth()->id(),
            ]);
        }
    }

    public function delete(int $id): void
    {
        Enquiry::find($id)?->delete();
    }

    public function render()
    {
        $enquiries = Enquiry::query()
            ->with('customer')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('subject', 'like', "%{$this->search}%")
                        ->orWhere('message', 'like', "%{$this->search}%")
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

        return view('livewire.enquiries.enquiry-list', compact('enquiries'));
    }
}
