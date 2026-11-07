<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorBooking extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['booked_at' => 'datetime', 'canceled_at' => 'datetime'];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(MentorSlot::class, 'slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
