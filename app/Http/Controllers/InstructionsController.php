<?php

namespace App\Http\Controllers;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstructionsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        Debugbar::info('User Context :', $user->user_context);
        Debugbar::info('Ai Context :', $user->ai_behavior);
        Debugbar::info('User custom command:', $user->custom_commands);

        return Inertia::render('Instructions/Index',
        [
            'user_context' => $user->user_context,
            'ai_behavior' => $user->ai_behavior,
            'custom_commands'=> $user->custom_commands ?? [],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate(
            [
                'user_context'=>'nullable|max:1500',
                'ai_behavior'=>'nullable|max:1500',
                'custom_commands'=>'nullable|array',
                'custom_commands.*.token'=>'required|string|max:25',
                'custom_commands.*.description'=>'required|string',
            ]);

        auth()->user()->update(
            [
                'user_context'=>$request->user_context,
                'ai_behavior'=>$request->ai_behavior,
                'custom_commands'=>$request->custom_commands,
            ]);

        return redirect()->back()->with('success','Instructions mise a jour');
    }

    public function list()
    {
        $user = auth()->user();

         return response()->json([
            'context' => $user->user_context,
            'context_ai' => $user->ai_behavior,
            'custom_commands' => $user->custom_commands ?? [],
        ]);
    }
}
