<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecoveryCode extends Model
{
    protected $table = 'recovery_codes';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['used_at' => 'datetime'];
}
