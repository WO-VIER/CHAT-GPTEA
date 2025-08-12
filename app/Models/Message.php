<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMessage
 */
class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessagesFactory> */
    use HasFactory;

    protected $fillable = ['conversation_id','context','model_id'];

    protected $casts = ['context' => 'array'];
    /**
     *  [
     *      {"role": "user","content":"...."},
     *      {"role": "user", "content" :"...."}
     *  ]
     *
     *
     *
     *
     *
     * */

    public function conversation() : BelongsTo
    {
        return $this->belongsTo(Conversation::class)->latest();
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id', 'model_id');
    }

    public function getMessageAssistant() : string
    {
        $aiMsg = collect($this->context)->firstWhere('role', 'assistant');
        return $aiMsg['content'] ?? '';
    }

    public function getMessageUser() : string
    {
        $userMsg = collect($this->context)->firstWhere('role', 'user');
        return $userMsg['content'] ?? '';
    }

    public static function mergeContext(string $messageUser, string $messageAssistant): array
    {
        return [
            ['role'=>'user', 'content' => $messageUser],
            ['role'=>'assistant', 'content' => $messageAssistant]];
    }



}
