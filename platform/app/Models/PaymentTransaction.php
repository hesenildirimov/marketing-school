<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'payment_transactions';

    protected $guarded = ['id'];

    protected $casts = ['occurred_at' => 'datetime', 'applied_at' => 'datetime'];
}
