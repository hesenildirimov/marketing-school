<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessPeriod extends Model
{
    public $timestamps = false;

    protected $table = 'access_periods';

    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime', 'created_at' => 'datetime'];
}
