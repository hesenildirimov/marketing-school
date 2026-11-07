<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['auto_stop_on_completion' => 'boolean'];

    /** Kurs üzrə qayda varsa o, yoxsa qlobal. */
    public static function for(?int $courseId): self
    {
        return ($courseId ? static::where('course_id', $courseId)->first() : null)
            ?? static::whereNull('course_id')->firstOrFail();
    }
}
