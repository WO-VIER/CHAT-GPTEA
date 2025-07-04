<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use App\Services\ConversationService;
use App\Http\Controllers\MessageController;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\Debugbar\Facades\Debugbar;
use OpenAI\Resources\Models;

class AskController extends Controller
{

    protected ConversationService $conversationService;

    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }

    public function index()
    {
        $chat = new ChatService();
        $modelsFromDb = $chat->getModelsWithIcons();
        $selectedModel = ChatService::DEFAULT_MODEL;

        /**
         * Array
         * [
         *  n : Array : Conversation orderby 'udated_at', 'asc'
         *      [
         *        n : Array : Message[] orderby 'created_at', 'desc'
         *      ]
         * ]
         */
        /**
         * Dois passer en props la liste des conversation de plus recent à plus ancien
         * et pour chaque conversation, la liste des messages de cette conversation
         */

        $conversations = $this->conversationService->getAllConversationsWithMessages();
        $currentConversationId = session('currentConversationId');
        Debugbar::info('Conversations:', $conversations);
        Debugbar::info('ID de conversation actuelle:', $currentConversationId);

        return Inertia::render('Ask/Index', [
            'modelsfromdb' => $modelsFromDb,
            'selectedModel' => $selectedModel,
            'conversations' => $conversations,
            'currentConversationId' => $currentConversationId,
        ]);
    }


    public function selectConversation(Request $request)
    {
        $conversationId = $request->conversation_id;

        // Si conversationId est null, on démarre une nouvelle conversation
        if ($conversationId) {
            // Vérifier que la conversation appartient à l'utilisateur
            $conversation = auth()->user()->conversations()->find($conversationId);
            if (!$conversation) {
                return redirect()->back()->with('error', 'Conversation non trouvée');
            }
        }

        // Mettre à jour la session
        session(['currentConversationId' => $conversationId]);

        return redirect()->back();
    }

    public function newConversation(Request $request)
    {
        session()->forget('currentConversationId');
        //$this->conversationService->createConv(auth()->user());
        return redirect()->back();
    }

    /*
    public function index()
    {
        $chat = new ChatService();
        $modelsFromDb = $chat->getModelsWithIcons();
        $selectedModel = ChatService::DEFAULT_MODEL;
        $conversations = auth()->user()->conversations(); //Toutes les conv dans ordre du plus recent -> ancien

        Debugbar::info($modelsFromDb);


        return Inertia::render('Ask/Index', [
            'modelsfromdb' => $modelsFromDb,
            'selectedModel' => $selectedModel,
        ]);
    }
    */

    public function list(): JsonResponse
    {
        $chat = new ChatService();
        $models = $chat->getModels();
        if (!is_array($models) || !$models)
            return response()->json(['message' => 'Pas de modele trouvé'], 500);
        return response()->json([
            'data' => $models,
            'count' => count($models),
        ]);
    }

    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
        ]);

        try {
            $messages = [[
                'role' => 'user',
                'content' => $request->message,
            ]];

            $response = (new ChatService())->sendMessage(
                messages: $messages,
                model: $request->model
            );

            return redirect()->back()->with('message', $response);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function finalAsk(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
        ]);

        try {

            $conversationId = session('currentConversationId');
            $conversation = null;
            $user = auth()->user();
            if ($conversationId) // Recupe la conv dans la session
                $conversation = $user->conversations()->find($conversationId);

            $result = $this->conversationService->buffAsk(user: $user, messageUser: $request->message, modelId: $request->model, conversation: $conversation);

            session(['currentConversationId' => $result['conversation']->id]);
            return redirect()->back()->with([
                'message' => $result['chatMessage'],
                'conversation_id' => $result['conversation']->id,
            ]);
        } catch (\Exception $e) {
            logger()->error(
                'Erreur dans AskController.finalAsk :',
                ['message' => $e->getMessage(), 'requete' => $request->all(),]
            );
            return redirect()->back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }
}
