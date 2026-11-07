<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['paid_until' => 'datetime', 'canceled_at' => 'datetime', 'modules_completed_at' => 'datetime',
        'mentoring_unlocked_at' => 'datetime', 'diploma_eligible_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class, 'course_version_id');
    }
}
