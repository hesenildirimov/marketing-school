<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $table = 'outbox';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['payload' => 'array', 'created_at' => 'datetime', 'available_at' => 'datetime', 'processed_at' => 'datetime'];
}
