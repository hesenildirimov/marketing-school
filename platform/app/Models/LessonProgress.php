<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = ['completed_at' => 'datetime', 'watch_percent' => 'float'];
}
