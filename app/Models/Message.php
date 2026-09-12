<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'uid', 'provider_uid', 'user_id', 'campaign_id', 'recipient',
        'sender_id', 'body', 'sms_type', 'segments', 'units', 'cost',
        'status', 'source', 'error', 'scheduled_at',
    ];

    const FINAL_STATUSES = ['Delivered', 'Failed', 'Rejected', 'Undelivered', 'Expired'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
