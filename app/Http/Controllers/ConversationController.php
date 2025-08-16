<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;

use Illuminate\Http\Request;

class ConversationController extends Controller
{



    public function conversation(Request $request)
    {
        $user = auth()->user();
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

            return redirect()->back()->with('message', dd($response));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }

    public function index(Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->user())
            abort(401, 'Acces non autorisé');

        $messages = $conversation->messages()->get();

        return response()->json(
            [
                'conversation' => $conversation,
                'messages' => $messages,
            ]
        );
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display all coversations for the authenticated user.
     */
    public function list() {}

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
