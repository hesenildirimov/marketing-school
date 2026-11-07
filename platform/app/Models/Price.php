<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['valid_from' => 'datetime'];

    public static function current(int $courseId): ?self
    {
        return static::where('course_id', $courseId)->where('valid_from', '<=', now())->orderByDesc('valid_from')->first();
    }

    public function display(): string
    {
        return rtrim(rtrim(number_format($this->amount_qepik / 100, 2, '.', ''), '0'), '.').' '.$this->currency;
    }
}
