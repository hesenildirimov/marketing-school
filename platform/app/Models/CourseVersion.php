<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseVersion extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['published_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
