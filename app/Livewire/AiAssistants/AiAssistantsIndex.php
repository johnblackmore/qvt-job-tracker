<?php

namespace App\Livewire\AiAssistants;

use App\Models\AiConversation;
use App\Models\AiDraftGeneration;
use App\Models\AiExtraction;
use App\Models\AiMessage;
use Livewire\Component;

class AiAssistantsIndex extends Component
{
    public function render()
    {
        $chatConversations = AiConversation::count();
        $chatMessages = AiMessage::count();
        $chatTokens = AiMessage::sum('input_tokens') + AiMessage::sum('output_tokens');
        $chatUsers = AiConversation::distinct('user_id')->count('user_id');

        $extractions = AiExtraction::count();
        $extractionsSuccess = AiExtraction::where('status', 'completed')->count();
        $extractionsFailed = AiExtraction::where('status', 'failed')->count();
        $extractionTokens = AiExtraction::sum('input_tokens') + AiExtraction::sum('output_tokens');

        $drafts = AiDraftGeneration::count();
        $draftTokens = AiDraftGeneration::sum('input_tokens') + AiDraftGeneration::sum('output_tokens');

        $totalTokens = $chatTokens + $extractionTokens + $draftTokens;

        return view('livewire.ai-assistants.ai-assistants-index', compact(
            'chatConversations', 'chatMessages', 'chatTokens', 'chatUsers',
            'extractions', 'extractionsSuccess', 'extractionsFailed', 'extractionTokens',
            'drafts', 'draftTokens',
            'totalTokens',
        ));
    }
}
