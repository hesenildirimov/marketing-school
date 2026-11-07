<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'occurrence_key' => 'date', 'meeting_url_encrypted' => 'encrypted'];

    protected $hidden = ['meeting_url_encrypted'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function endsAt(): CarbonInterface
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }
}
