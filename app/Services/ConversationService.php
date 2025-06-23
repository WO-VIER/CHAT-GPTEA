<?php

namespace App\Services;

use App\Actions\Jetstream\CreateTeam;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

use App\Models\ProviderIcon;
use Illuminate\Support\Facades\Http;

use App\Models\AiModel;
use Barryvdh\Debugbar\Facades\Debugbar;
use GuzzleHttp\Promise\Create;
use OpenAI\Responses\Chat\CreateResponseMessage;




use function Pest\Laravel\json;

class ConversationService
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function getAllConversationsWithMessages(): array
    {
        $user = auth()->user();
        return $user->conversations()->with('messages')->orderBy('updated_at', 'desc')->get()->map(function ($conversation) {
            return [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updated_at' => $conversation->updated_at->diffForHumans(),
                'messages' => $conversation->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'user_message' => $message->getMessageUser(),
                        'ai_message' => $message->getMessageAssistant(),
                        'created_at' => $message->created_at,
                    ];
                }),
            ];
        })->toArray();
    }

    public function getMessagesByConversationById(int $conversationId)
    {
        if (!$conversationId)
            return;
        $conversation = auth()->user()->conversations()->with(['messages'])->find($conversationId);
        $messages = $conversation->messages->map(function ($message) {
            return [
                'id' => $message->id,
                'user_message' => $message->getMessageUser(),
                'assistant_message' => $message->getMessageAssistant(),
                'created_at' => $message->created_at,
            ];
        });

        return ($messages);
    }

    public function getAllMessages(?Conversation $conversation = null, ?int $id = 0, ?string $title = null): array
    {
        if ($conversation)
            return $conversation->messages()->get()->toArray();
        if ($id && $title)
            return Conversation::where('id', '=', $id)->where('title', '=', $title)->messages()->get()->toArray();
        if ($id)
            return Conversation::find($id)->messages()->get()->toArray();
        if ($title)
            return Conversation::where('title', '=', $title)->messages()->get()->toArray();
        return [];
    }

    public function createConv(User $user): Conversation
    {
        return Conversation::create(['user_id' => $user->id, 'title' => 'Nouveau chat']);
    }

    public function findOrCreateConv(User $user): Conversation
    {
        //lates = orderby 'created_at' desc
        //$conversation = Conversation::where('user_id','=',$user->id)->latest()->first();
        $conversation = $user->conversations()->latest()->first();

        if (!$conversation)
            $conversation = $this->createConv($user);
        return $conversation;
    }

    //public function continueConversation(Conversation $conversation, User $ ){}

    public function buffAsk(User $user, string $messageUser, string $modelId, ?Conversation $conversation = null): array
    {
        try {
            //$parsed = $this->chatService->parseMessage($messageUser);
            //dd($parsed);
            //dd($conversation);
            if (!$conversation)
                $conversation = $this->findOrCreateConv($user);
            //On recupere tout les messages linker de "Conversation"
            $allMessages = $conversation->getAllMessages();
            Debugbar::info($allMessages);
            $allMessages[] = ['role' => 'user', 'content' => $messageUser];


            $chatMessage = $this->chatService->sendMessage(messages: $allMessages, model: $modelId);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'context' => Message::mergeContext($messageUser, $chatMessage),
                'model_id' => $modelId,
            ]);

            if ($conversation->messages()->count() === 1 || $conversation->title === 'Nouveau chat' || $conversation->title === '')
                $conversation->update(['title' => $conversation->createTitle()]);

            $conversation->touch();

            return ([
                'success' => true,
                'conversation' => $conversation->fresh('messages'),
                'message' => $message,
                'userMessage' => $messageUser,
                'chatMessage' => $chatMessage,
            ]);
        } catch (\Exception $e) {
            logger()->error('Erreur lors du traitement du message dans buffAsk:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'user_message' => $messageUser,
                'model_id' => $modelId,
            ]);

            throw $e;
        }
    }
}
