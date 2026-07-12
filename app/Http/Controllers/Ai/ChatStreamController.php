<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\Assistants\ChatAgentAssistant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    public function __invoke(AiConversation $conversation, ChatAgentAssistant $assistant): StreamedResponse
    {
        abort_if($conversation->user_id !== request()->user()->id, 403);

        $data = request()->validate([
            'message' => 'sometimes|string|max:4000',
        ]);

        session_write_close();

        return $assistant->streamResponse(
            conversation: $conversation,
            user: request()->user(),
            newMessage: $data['message'] ?? null,
        );
    }
}
