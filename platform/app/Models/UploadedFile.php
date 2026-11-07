<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['scanned_at' => 'datetime', 'quarantined_at' => 'datetime'];

    public function isDownloadable(): bool
    {
        return $this->scan_status === 'clean' && $this->quarantined_at === null;
    }
}
