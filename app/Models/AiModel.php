<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAiModel
 */
class AiModel extends Model
{
    protected $fillable = ['model_id', 'name', 'provider_name',
    'context_length', 'max_completion_tokens', 'pricing', 'is_active' ];

    protected $casts =
    [
        'pricing' => 'array', 'is_active' => 'boolean',
        'context_length' => 'integer', 'max_completion_tokens' => 'integer'
    ];

    public function providerIcon()
    {
        //Va chercher dans ProviderIcon la foreign key name  'provider_name' -> 'name'
        return $this->belongsTo(ProviderIcon::class, 'provider_name', 'name');
    }
}
