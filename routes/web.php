<?php

use Illuminate\Foundation\Application;
use App\Http\Controllers\AskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AiModelController;
use App\Http\Controllers\ProviderIconController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\InstructionsController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\TestApi;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\Mime\MessageConverter;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('ask.index');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
/*
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
*/
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::prefix('/ask')->group(function () {
        Route::get('/', [AskController::class, 'index'])->name('ask.index');
        Route::post('/', [AskController::class, 'finalAsk'])->name('ask.post');
        Route::post('/new-conversation', [AskController::class, 'newConversation'])->name('ask.newConversation');
        Route::post('/select-conversation', [AskController::class, 'selectConversation'])->name('ask.select-conversation');
        Route::get('/debug', [AskController::class, 'debug'])->name('ask.debug');
    });

    Route::post('/stream', [StreamController::class, 'streamMessage'])->name('stream.message');

    Route::prefix('/instructions')->group(function () {
        Route::get('/', [InstructionsController::class, 'index'])->name('instructions.index');
        Route::post('/', [InstructionsController::class, 'update'])->name('instructions.post');
        Route::get('/list', [InstructionsController::class, 'list'])->name('instructions.list');
    });

    Route::get('/message/index/{id}', [MessageController::class, 'index'])->name('message.index');
    Route::post('/message/{conversation}', [MessageController::class, 'store'])->name('message.store');

    Route::prefix('/dev/openrouter')->group(function () {
        Route::get('/getModels/api', [AskController::class, 'list'])->name('ask.list');
    });

    Route::prefix('dev/aimodel')->group(function () {
        Route::get('populate', [AiModelController::class, 'populateFromApiGetModels'])->name('aimodel.populate');
    });

    Route::prefix('dev/user/')->group(function () {
        Route::get('{id}', [UserController::class, 'show'])->name('user.show');
    });
});

Route::get('/clear-cache', function () {
    Cache::flush();
    return 'Cache vidé avec succès ! <br><a href="/ask">Retourner à l\'application</a>';
});


Route::prefix('dev/provider-icons')->middleware(['auth'])->group(function () {
    Route::get('/', [ProviderIconController::class, 'index'])->name('provider-icons.index');
    Route::get('/list', [ProviderIconController::class, 'list'])->name('provider-icons.list');
    Route::get('/populate', [ProviderIconController::class, 'populate'])->name('provider-icons.populate');
});

Route::prefix('dev/ai-models')->middleware(['auth'])->group(function () {
    Route::get('/', [AiModelController::class, 'index'])->name('ai-models.index');
    Route::get('/list', [AiModelController::class, 'list'])->name('ai-models.list');
    Route::get('/populate', [AiModelController::class, 'populate'])->name('ai-models.populate');
    Route::get('/populate/null', [AiModelController::class, 'nullproviderIcons'])->name('ai-models.nullproviderIcons');
    Route::get('/populateNoAuth', [AiModelController::class, 'populateNotAuth'])->name('ai-models.populateNotAuth');
});

Route::prefix('dev/conversations')->middleware(['auth'])->group(function () {
    Route::get('/', function () {
        $user = auth()->user()->conversations()->with('messages')->get();
        return response()->json($user);
    });
});
