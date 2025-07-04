<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperConversation
 */
class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationsFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return ($this->belongsTo(User::class));
    }

    public function messages() : HasMany
    {
        return($this->hasMany(Message::class));
    }

    public function latestMessages(): HasMany
    {
        return ($this->hasMany(Message::class)->latest());
    }

    public function createTitle(): string
    {
        $firstMessage = $this->messages()->first();

        if(!$firstMessage)
            return 'Nouveau chat';

        return ($firstMessage->getMessageUser() ?? 'Nouveau chat');
    }
    /**
     *  [
     *      {"role": "user","content":"...."},
     *      {"role": "user", "content" :"...."}
     *  ]
     *  Pour un message Une question et une réponse
     * */
    function getAllMessages(): array
    {
        $messages = [];
        foreach($this->messages as $message)
        {
            foreach($message->context as $mixedContext)
            {
                $messages[] =
                [
                    'role' =>$mixedContext['role'],
                    'content' => $mixedContext['content'],
                ];
            }
        }
        return $messages;
    }
}
