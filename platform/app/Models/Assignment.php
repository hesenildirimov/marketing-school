<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $table = 'assignments';

    protected $guarded = ['id'];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(AssignmentRevision::class, 'current_revision_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function label(): string
    {
        return tr_field($this->revision?->title);
    }
}
