<?php

namespace App\Http\Controllers;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\ChatService;
use Illuminate\Http\Request;

class TestApi extends Controller
{
    public function SimpleTestChatgpt()
    {
        try {
            $stream  = OpenAI::chat()->createStreamed([
            'model' => 'openai/gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => 'Salut donne moi une recette de tarte a la pomme'],
            ],
        ]);
       foreach($stream as $response)
        {
            echo($response->choices[0]->delta->content);
        }

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
    }
}
