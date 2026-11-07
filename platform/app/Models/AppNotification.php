<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'scheduled_for' => 'datetime', 'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime', 'read_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
