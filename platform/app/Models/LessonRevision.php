<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonRevision extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['title' => 'array', 'body' => 'array', 'chapters' => 'array', 'materials' => 'array', 'created_at' => 'datetime'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class, 'video_asset_id');
    }
}
