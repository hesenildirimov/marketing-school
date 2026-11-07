<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthToken extends Model
{
    protected $table = 'auth_tokens';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
