<?php

namespace App\Livewire\Enquiries;

use App\Models\Enquiry;
use App\Models\EnquiryActivityLog;
use App\Models\EnquiryReply;
use App\Models\User;
use App\Services\EnquiryAiAssistantService;
use App\Services\EnquiryReplyService;
use Livewire\Component;

class EnquiryShow extends Component
{
    public Enquiry $enquiry;

    public string $replySubject = '';

    public string $replyBody = '';

    public string $aiDraftConfidence = '';

    public ?array $aiSuggestedSteps = null;

    public ?array $aiKnowledgeGaps = null;

    public bool $showAiDraft = false;

    public bool $sending = false;

    protected function rules(): array
    {
        return [
            'replySubject' => ['nullable', 'string', 'max:255'],
            'replyBody' => ['required', 'string'],
        ];
    }

    public function mount(int $enquiryId): void
    {
        $this->enquiry = Enquiry::with([
            'customer',
            'staff',
            'replies' => fn ($q) => $q->with('staff')->orderByDesc('created_at'),
            'quotes',
            'activityLogs' => fn ($q) => $q->with('staff'),
        ])->findOrFail($enquiryId);

        $this->replySubject = 'Re: '.($this->enquiry->subject ?? 'Your Enquiry');
    }

    public function generateAiDraft(): void
    {
        try {
            $service = app(EnquiryAiAssistantService::class);
            $draft = $service->generateDraft($this->enquiry);

            if (! empty($draft['error'])) {
                $this->dispatch('notify', type: 'error', message: 'AI draft failed: '.$draft['error']);

                return;
            }

            if (! empty($draft['draft_subject'])) {
                $this->replySubject = $draft['draft_subject'];
            }
            if (! empty($draft['draft_body'])) {
                $this->replyBody = $draft['draft_body'];
            }

            $this->aiDraftConfidence = $draft['confidence'] ?? 'low';
            $this->aiSuggestedSteps = $draft['suggested_next_steps'] ?? [];
            $this->aiKnowledgeGaps = $draft['knowledge_gaps'] ?? [];
            $this->showAiDraft = true;

            $this->dispatch('notify', type: 'success', message: 'AI draft generated. Review and edit before sending.');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to generate AI draft: '.$e->getMessage());
        }
    }

    public function discardAiDraft(): void
    {
        $this->replyBody = '';
        $this->replySubject = 'Re: '.($this->enquiry->subject ?? 'Your Enquiry');
        $this->showAiDraft = false;
        $this->aiDraftConfidence = '';
        $this->aiSuggestedSteps = null;
        $this->aiKnowledgeGaps = null;
    }

    public function sendReply(): void
    {
        $this->validate();

        $this->sending = true;

        try {
            $service = app(EnquiryReplyService::class);
            $service->send($this->enquiry, [
                'subject' => $this->replySubject,
                'body' => $this->replyBody,
            ]);

            $this->dispatch('notify', type: 'success', message: 'Reply sent successfully.');

            $this->reset(['replyBody', 'showAiDraft', 'aiDraftConfidence', 'aiSuggestedSteps', 'aiKnowledgeGaps']);
            $this->replySubject = 'Re: '.($this->enquiry->subject ?? 'Your Enquiry');

            $this->enquiry->refresh();
            $this->enquiry->load([
                'replies' => fn ($q) => $q->with('staff')->orderByDesc('created_at'),
                'activityLogs' => fn ($q) => $q->with('staff'),
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to send reply: '.$e->getMessage());
        } finally {
            $this->sending = false;
        }
    }

    public function resendReply(int $replyId): void
    {
        try {
            $originalReply = EnquiryReply::findOrFail($replyId);

            $service = app(EnquiryReplyService::class);
            $service->resend($originalReply);

            $this->dispatch('notify', type: 'success', message: 'Reply resent successfully.');

            $this->enquiry->refresh();
            $this->enquiry->load([
                'replies' => fn ($q) => $q->with('staff')->orderByDesc('created_at'),
                'activityLogs' => fn ($q) => $q->with('staff'),
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to resend reply: '.$e->getMessage());
        }
    }

    public function markInProgress(): void
    {
        $this->enquiry->update(['status' => 'in_progress']);

        EnquiryActivityLog::create([
            'enquiry_id' => $this->enquiry->id,
            'staff_user_id' => auth()->id(),
            'action' => 'status_changed',
            'description' => 'Status changed to in_progress',
        ]);

        $this->enquiry->refresh();
        $this->dispatch('notify', type: 'success', message: 'Enquiry marked as in progress.');
    }

    public function close(): void
    {
        $this->enquiry->update(['status' => 'closed']);

        EnquiryActivityLog::create([
            'enquiry_id' => $this->enquiry->id,
            'staff_user_id' => auth()->id(),
            'action' => 'status_changed',
            'description' => 'Status changed to closed',
        ]);

        $this->enquiry->refresh();
        $this->dispatch('notify', type: 'success', message: 'Enquiry closed.');
    }

    public function assignStaff(int $userId): void
    {
        $this->enquiry->update(['staff_user_id' => $userId]);

        EnquiryActivityLog::create([
            'enquiry_id' => $this->enquiry->id,
            'staff_user_id' => auth()->id(),
            'action' => 'assigned',
            'description' => 'Assigned to '.(User::find($userId)?->name ?? 'unknown'),
            'metadata' => ['assigned_user_id' => $userId],
        ]);

        $this->enquiry->refresh();
        $this->dispatch('notify', type: 'success', message: 'Enquiry assigned.');
    }

    public function render()
    {
        $staffMembers = User::role(['admin', 'installer'])->orderBy('name')->get();

        return view('livewire.enquiries.enquiry-show', compact('staffMembers'));
    }
}
