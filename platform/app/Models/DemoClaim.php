<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoClaim extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'expires_at' => 'datetime', 'email_confirmed_at' => 'datetime',
        'converted_at' => 'datetime', 'converted_other_at' => 'datetime', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isActive(): bool
    {
        return now()->lt($this->expires_at);
    }
}
