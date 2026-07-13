<?php

namespace App\Livewire\Enquiries;

use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class EnquiryList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $staffUserId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function markResponded(int $id): void
    {
        $enquiry = Enquiry::find($id);
        if ($enquiry) {
            $enquiry->update([
                'status' => 'responded',
                'responded_at' => now(),
                'staff_user_id' => auth()->id(),
            ]);

            EnquiryActivityLog::create([
                'enquiry_id' => $enquiry->id,
                'staff_user_id' => auth()->id(),
                'action' => 'status_changed',
                'description' => 'Status changed to responded',
            ]);
        }
    }

    public function assignStaff(int $enquiryId, int $userId): void
    {
        $enquiry = Enquiry::find($enquiryId);
        if ($enquiry) {
            $enquiry->update(['staff_user_id' => $userId]);

            EnquiryActivityLog::create([
                'enquiry_id' => $enquiry->id,
                'staff_user_id' => auth()->id(),
                'action' => 'assigned',
                'description' => 'Assigned to '.(User::find($userId)?->name ?? 'unknown'),
                'metadata' => ['assigned_user_id' => $userId],
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
            ->with(['customer', 'staff', 'latestReply'])
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
            ->when($this->staffUserId, function ($query) {
                $query->where('staff_user_id', $this->staffUserId);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        $staffMembers = User::role(['admin', 'installer'])->orderBy('name')->get();

        return view('livewire.enquiries.enquiry-list', compact('enquiries', 'staffMembers'));
    }
}
