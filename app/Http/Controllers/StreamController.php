<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Services\ChatService;
use App\Services\ConversationService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;


class StreamController extends Controller
{
    protected ChatService $chatService;
    protected ConversationService $conversationService;

    public function __construct(ChatService $chatService, ConversationService $conversationService)
    {
        $this->chatService = $chatService;
        $this->conversationService = $conversationService;
    }

    public function streamMessage(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'model' => 'required|string',
            'conversation_id' => 'nullable|integer',
        ]);

        logger()->info('streamMessage - Début', [
            'model_recu ' => $request->model,
            'message' => $request->text,
            'conversation_id' => $request->conversation_id,
            'user_id' => auth()->id()
        ]);

        $user = auth()->user();
        $conversationId = $request->conversation_id;
        $conversation = null;

        if ($conversationId) {
            $conversation = $user->conversations()->find($conversationId);
        }

        if (!$conversation) {
            $conversation = $this->conversationService->createConv($user);
            session(['currentConversationId' => $conversation->id]);
            logger()->info('Nouvelle conversation créée', ['conversation_id' => $conversation->id]);
        }

        $allMessages = $conversation->getAllMessages();


        logger()->info('CONVERSATION complète', [
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->title,
            'nb_messages_historique' => count($allMessages),
            'messages_historique' => $allMessages
        ]);

        $allMessages[] = ['role' => 'user', 'content' => $request->text];
        return response()->stream(function () use ($allMessages, $request, $conversation, $user) {
            $fullResponce = '';

            try {
                //Recupére le strean response pour SSE
                $stream = $this->chatService->sendMessageStream(
                    messages: $allMessages,
                    model: $request->model
                );


                foreach ($stream as $responce) {
                    $content = $responce->choices[0]->delta->content ?? '';
                    $fullResponce .= $content;
                    yield $content;
                }

                Message::create([
                    'conversation_id' => $conversation->id,
                    'context' => Message::mergeContext($request->text, $fullResponce),
                    'model_id' => $request->model,
                ]);

                if ($conversation->messages()->count() === 1 || $conversation->title === 'Nouveau chat' || $conversation->title === '')
                    $conversation->update(['title' => $conversation->createTitle()]);

                $conversation->touch();
            } catch (\Exception $e) {
                logger()->error(
                    'Erreur dans streaming :',
                    ['message' => $e->getMessage(), 'user_id' => $user->id,]
                );
                yield "Erreur : " . $e->getMessage();
            }
        }, 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
