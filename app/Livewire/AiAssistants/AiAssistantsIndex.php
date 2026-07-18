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
        $extractionTokens = AiExtraction::sum('input_tokens') + AiExtraction::sum('output_tokens');

        $productExtractions = AiExtraction::where('assistant_name', 'product-url-extractor')->count();
        $productExtractionsSuccess = AiExtraction::where('assistant_name', 'product-url-extractor')->where('status', 'completed')->count();
        $productExtractionsSuccessRate = $productExtractions > 0 ? round($productExtractionsSuccess / $productExtractions * 100, 2) : 0;
        $productExtractionTokens = AiExtraction::where('assistant_name', 'product-url-extractor')->sum('input_tokens')
            + AiExtraction::where('assistant_name', 'product-url-extractor')->sum('output_tokens');

        $drafts = AiDraftGeneration::count();
        $draftTokens = AiDraftGeneration::sum('input_tokens') + AiDraftGeneration::sum('output_tokens');

        $expensesExtractions = AiExtraction::where('assistant_name', 'expenses-extractor')->count();
        $expensesExtractionsSuccess = AiExtraction::where('assistant_name', 'expenses-extractor')->where('status', 'completed')->count();
        $expensesExtractionsSuccessRate = $expensesExtractions > 0 ? round($expensesExtractionsSuccess / $expensesExtractions * 100, 2) : 0;
        $expensesExtractionTokens = AiExtraction::where('assistant_name', 'expenses-extractor')->sum('input_tokens')
            + AiExtraction::where('assistant_name', 'expenses-extractor')->sum('output_tokens');

        $totalTokens = $chatTokens + $extractionTokens + $draftTokens + $expensesExtractionTokens;

        return view('livewire.ai-assistants.ai-assistants-index', compact(
            'chatConversations', 'chatMessages', 'chatTokens', 'chatUsers',
            'extractions', 'extractionTokens',
            'productExtractions', 'productExtractionsSuccessRate', 'productExtractionTokens',
            'drafts', 'draftTokens',
            'expensesExtractions', 'expensesExtractionsSuccessRate', 'expensesExtractionTokens',
            'totalTokens',
        ));
    }
}
