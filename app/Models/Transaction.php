<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'type', 'units', 'balance_after', 'amount', 'note', 'ref', 'actor',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
