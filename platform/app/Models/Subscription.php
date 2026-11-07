<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['provider_ref_encrypted'];

    protected $casts = ['next_charge_at' => 'datetime', 'canceled_at' => 'datetime', 'cancel_at_period_end' => 'boolean',
        'provider_ref_encrypted' => 'encrypted'];
}
