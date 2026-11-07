<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $table = 'certificates';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['snapshot' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
}
