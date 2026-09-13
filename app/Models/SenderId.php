<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderId extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'mask', 'status', 'note', 'fee_units', 'fee_amount'];

    protected $casts = ['fee_amount' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
