<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    protected $table = 'submissions';

    protected $guarded = ['id'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(UploadedFile::class);
    }

    public function currentGrade(): HasOne
    {
        return $this->hasOne(Grade::class)->whereNull('superseded_at');
    }
}
