<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Group extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'provider_uid', 'name', 'contacts'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
