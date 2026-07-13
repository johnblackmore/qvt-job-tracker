<div>
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('enquiries.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-copper transition-colors mb-2">
                <x-lucide-arrow-left class="w-4 h-4" />
                Back to Enquiries
            </a>
            <h1 class="text-2xl font-display font-semibold text-slate-900 tracking-tight">{{ $enquiry->subject ?? 'Enquiry #'.$enquiry->id }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                From {{ $enquiry->customer?->name ?? $enquiry->from_email ?? 'Unknown' }}
                &middot; {{ $enquiry->created_at->format('j F Y, g:ia') }}
                &middot; {{ ucfirst($enquiry->source) }}
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('enquiries.edit', $enquiry) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                <x-lucide-pencil class="w-4 h-4" />
                Edit
            </a>
            @if($enquiry->status === 'new')
                <button wire:click="markInProgress" class="inline-flex items-center gap-2 rounded-lg bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 hover:bg-blue-100 transition-colors">
                    <x-lucide-play class="w-4 h-4" />
                    Start
                </button>
            @endif
            @if($enquiry->status !== 'closed')
                <button wire:click="close" wire:confirm="Close this enquiry?" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                    <x-lucide-check-circle class="w-4 h-4" />
                    Close
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Original enquiry --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-copper/10 flex items-center justify-center">
                            <x-lucide-message-circle class="w-5 h-5 text-copper" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900">Original Enquiry</p>
                            <p class="text-xs text-slate-500">{{ $enquiry->created_at->format('j F Y, g:ia') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ $enquiry->status === 'new' ? 'bg-amber-50 text-amber-700 border border-amber-200' : '' }}
                        {{ $enquiry->status === 'responded' ? 'bg-teal/10 text-teal-dark border border-teal/20' : '' }}
                        {{ $enquiry->status === 'in_progress' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '' }}
                        {{ $enquiry->status === 'closed' ? 'bg-slate-100 text-slate-600 border border-slate-200' : '' }}
                    ">
                        {{ ucfirst(str_replace('_', ' ', $enquiry->status)) }}
                    </span>
                </div>
                <div class="bg-slate-50 rounded-lg p-4 text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">
                    {{ $enquiry->message }}
                </div>
                @if($enquiry->internal_notes)
                    <div class="mt-4 pt-4 border-t border-slate-200">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Internal Notes</p>
                        <p class="text-sm text-slate-600 whitespace-pre-wrap">{{ $enquiry->internal_notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Reply thread --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-4">Conversation Thread</h2>

                @if($enquiry->replies->isEmpty() && !$showAiDraft)
                    <p class="text-sm text-slate-500">No replies yet. Compose your first reply below.</p>
                @else
                    <div class="space-y-4">
                        @foreach($enquiry->replies as $reply)
                            <div class="flex gap-3 {{ $reply->direction === 'inbound' ? '' : 'flex-row-reverse' }}">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 {{ $reply->direction === 'inbound' ? 'bg-slate-100' : 'bg-copper/10' }}">
                                    @if($reply->direction === 'inbound')
                                        <x-lucide-user class="w-4 h-4 text-slate-500" />
                                    @else
                                        <x-lucide-send class="w-4 h-4 text-copper" />
                                    @endif
                                </div>
                                <div class="max-w-[80%] {{ $reply->direction === 'inbound' ? '' : 'text-right' }}">
                                    <div class="inline-block rounded-lg px-4 py-3 text-sm {{ $reply->direction === 'inbound' ? 'bg-slate-100 text-slate-700' : 'bg-copper/5 text-slate-700' }}">
                                        @if($reply->subject)
                                            <p class="font-medium text-slate-900 mb-1">{{ $reply->subject }}</p>
                                        @endif
                                        <p class="whitespace-pre-wrap">{{ $reply->body }}</p>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ $reply->staff?->name ?? ($reply->direction === 'inbound' ? 'Customer' : 'Staff') }}
                                        &middot; {{ $reply->created_at->format('j M Y, g:ia') }}
                                        @if($reply->status === 'sent' && $reply->sent_at)
                                            &middot; Sent
                                        @elseif($reply->status === 'failed')
                                            &middot; <span class="text-red-500">Failed</span>
                                        @elseif($reply->status === 'draft')
                                            &middot; Draft
                                        @elseif($reply->status === 'received')
                                            &middot; Received
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach

                        {{-- AI draft preview --}}
                        @if($showAiDraft)
                            <div class="flex gap-3 flex-row-reverse">
                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center shrink-0">
                                    <x-lucide-bot class="w-4 h-4 text-purple-600" />
                                </div>
                                <div class="max-w-[80%] text-right">
                                    <div class="inline-block rounded-lg px-4 py-3 text-sm bg-purple-50 border border-purple-200 text-slate-700">
                                        <p class="font-medium text-purple-700 mb-1">{{ $replySubject }}</p>
                                        <p class="whitespace-pre-wrap">{{ $replyBody }}</p>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 mt-1">
                                        <span class="text-xs text-slate-400">AI Draft</span>
                                        @if($aiDraftConfidence === 'high')
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-50 text-green-700">High confidence</span>
                                        @elseif($aiDraftConfidence === 'medium')
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700">Medium confidence</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-50 text-red-700">Low confidence</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Compose reply --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-900 mb-4">Compose Reply</h2>

                <form wire:submit="sendReply" class="space-y-4">
                    <div>
                        <label for="replySubject" class="block text-sm font-medium text-slate-700 mb-1.5">Subject</label>
                        <input wire:model="replySubject" id="replySubject" type="text" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" />
                        @error('replySubject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="replyBody" class="block text-sm font-medium text-slate-700 mb-1.5">Message</label>
                        <textarea wire:model="replyBody" id="replyBody" rows="6" class="w-full rounded-lg border-slate-300 text-slate-900 focus:border-copper focus:ring-copper text-sm px-3.5 py-2.5" placeholder="Type your reply..."></textarea>
                        @error('replyBody') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- AI suggested next steps --}}
                    @if($showAiDraft && $aiSuggestedSteps)
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm font-medium text-blue-800 mb-2">Suggested Next Steps</p>
                            <ul class="space-y-1">
                                @foreach($aiSuggestedSteps as $step)
                                    <li class="text-sm text-blue-700 flex items-start gap-2">
                                        <x-lucide-lightbulb class="w-4 h-4 mt-0.5 shrink-0" />
                                        <span>{{ $step }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- AI knowledge gaps --}}
                    @if($showAiDraft && $aiKnowledgeGaps && count($aiKnowledgeGaps) > 0)
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <p class="text-sm font-medium text-amber-800 mb-2">Knowledge Gaps</p>
                            <ul class="space-y-1">
                                @foreach($aiKnowledgeGaps as $gap)
                                    <li class="text-sm text-amber-700 flex items-start gap-2">
                                        <x-lucide-help-circle class="w-4 h-4 mt-0.5 shrink-0" />
                                        <span>{{ $gap }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="flex items-center justify-between pt-2">
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="generateAiDraft" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg border border-purple-300 px-4 py-2.5 text-sm font-medium text-purple-700 hover:bg-purple-50 transition-colors">
                                <x-lucide-bot class="w-4 h-4" />
                                <span wire:loading.remove wire:target="generateAiDraft">Generate AI Draft</span>
                                <span wire:loading wire:target="generateAiDraft">Generating...</span>
                            </button>
                            @if($showAiDraft)
                                <button type="button" wire:click="discardAiDraft" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                                    <x-lucide-x class="w-4 h-4" />
                                    Discard Draft
                                </button>
                            @endif
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="sendReply" class="inline-flex items-center gap-2 rounded-lg bg-copper px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-copper-dark transition-colors whitespace-nowrap">
                            <span wire:loading.remove wire:target="sendReply" class="inline-flex items-center gap-2">
                                <x-lucide-send class="w-4 h-4 shrink-0" />
                                Send Reply
                            </span>
                            <span wire:loading wire:target="sendReply" class="inline-flex items-center gap-2">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Customer info --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Customer</h3>
                @if($enquiry->customer)
                    <a href="{{ route('customers.show', $enquiry->customer) }}" wire:navigate class="flex items-center gap-3 group">
                        <div class="w-10 h-10 rounded-full bg-teal/10 flex items-center justify-center">
                            <x-lucide-user class="w-5 h-5 text-teal-dark" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900 group-hover:text-copper transition-colors">{{ $enquiry->customer->name }}</p>
                            @if($enquiry->customer->email)
                                <p class="text-xs text-slate-500">{{ $enquiry->customer->email }}</p>
                            @endif
                        </div>
                    </a>
                @else
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center">
                            <x-lucide-user class="w-5 h-5 text-slate-400" />
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">Unlinked enquiry</p>
                            @if($enquiry->email)
                                <p class="text-xs text-slate-500">{{ $enquiry->email }}</p>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('enquiries.edit', $enquiry) }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-xs text-copper hover:text-copper-dark">
                        <x-lucide-link class="w-3 h-3" />
                        Link to customer
                    </a>
                @endif
            </div>

            {{-- Assigned staff --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Assigned To</h3>
                <select wire:change="assignStaff($event.target.value)" class="w-full rounded-lg border-slate-300 text-slate-900 text-sm px-3 py-2 focus:border-copper focus:ring-copper">
                    <option value="">Unassigned</option>
                    @foreach($staffMembers as $staff)
                        <option value="{{ $staff->id }}" @selected(optional($enquiry->staff)->id === $staff->id)>{{ $staff->name }}</option>
                    @endforeach
                </select>
                @if($enquiry->staff)
                    <p class="text-xs text-slate-400 mt-2">
                        Currently: {{ $enquiry->staff->name }}
                    </p>
                @endif
            </div>

            {{-- Linked quotes --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Quotes</h3>
                    <a href="{{ route('quotes.create', ['enquiryId' => $enquiry->id]) }}" wire:navigate class="inline-flex items-center gap-1 text-xs text-copper hover:text-copper-dark">
                        <x-lucide-plus class="w-3 h-3" />
                        Create Quote
                    </a>
                </div>
                @if($enquiry->quotes->isEmpty())
                    <p class="text-sm text-slate-500">No quotes linked yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach($enquiry->quotes as $quote)
                            <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="block p-3 rounded-lg border border-slate-200 hover:border-copper/30 hover:bg-copper/5 transition-colors">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-slate-900">{{ $quote->reference_number }}</span>
                                    <span class="text-sm font-semibold text-slate-900">&pound;{{ number_format($quote->grand_total, 2) }}</span>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium mt-1
                                    {{ $quote->status === 'draft' ? 'bg-slate-100 text-slate-600' : '' }}
                                    {{ $quote->status === 'sent' ? 'bg-blue-50 text-blue-700' : '' }}
                                    {{ $quote->status === 'accepted' ? 'bg-teal/10 text-teal-dark' : '' }}
                                    {{ $quote->status === 'declined' ? 'bg-red-50 text-red-700' : '' }}
                                ">
                                    {{ ucfirst($quote->status) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Activity log --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Activity</h3>
                @if($enquiry->activityLogs->isEmpty())
                    <p class="text-sm text-slate-500">No activity recorded.</p>
                @else
                    <div class="space-y-3">
                        @foreach($enquiry->activityLogs as $log)
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full mt-1.5 shrink-0
                                    {{ $log->action === 'reply_sent' ? 'bg-copper' : '' }}
                                    {{ $log->action === 'quote_created' ? 'bg-teal-dark' : '' }}
                                    {{ $log->action === 'status_changed' ? 'bg-blue-500' : '' }}
                                    {{ $log->action === 'assigned' ? 'bg-purple-500' : '' }}
                                    {{ $log->action === 'note_added' ? 'bg-slate-400' : '' }}
                                "></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-slate-700">{{ $log->description }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $log->staff?->name ?? 'System' }}
                                        &middot; {{ $log->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
