<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Services\ChatService;
use App\Services\ConversationService;
use DebugBar\DebugBar;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MessageController extends Controller
{

    protected ConversationService $conversationService;
    public function __construct(ConversationService $conversationService)
    {
        $this->conversationService = $conversationService;
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id())
            abort(401, 'Acces non autorisé');

        $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
        ]);

        try {
            $result = $this->conversationService->buffAsk(user: auth()->user(), messageUser: $request->message, modelId: $request->model);

            return response()->json([
                'success' => true,
                'conversation' => $result['conversation'],
                'message' => $result['message'],
                'userMessage' => $result['messageUser'],
                'chatMessage' => $result['chatMessage'],
            ]);
        } catch (\Exception $e) {
            DebugBar::error($e->getMessage());
            return response()->json(['sucess' => false, 'Porbleme lors de l\'envoie dans MessageContoller.store '], 500);
        }
    }

    public function index(Conversation $conversation) {}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
