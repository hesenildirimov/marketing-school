<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorSecret extends Model
{
    protected $table = 'two_factor_secrets';

    protected $guarded = [];

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $casts = ['confirmed_at' => 'datetime', 'secret_encrypted' => 'encrypted'];
}
