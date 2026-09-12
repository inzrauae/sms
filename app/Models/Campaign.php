<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'uid', 'user_id', 'name', 'group_uid', 'group_name', 'sender_id',
        'body', 'sms_type', 'contacts', 'units', 'cost', 'status',
        'provider_ref', 'scheduled_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
