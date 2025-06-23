<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProviderIcon
 */
class ProviderIcon extends Model
{
    protected $fillable = ['name', 'url'];
}
