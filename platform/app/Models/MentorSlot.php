<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorSlot extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'deleted_at' => 'datetime', 'meeting_url_encrypted' => 'encrypted'];

    protected $hidden = ['meeting_url_encrypted'];

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
