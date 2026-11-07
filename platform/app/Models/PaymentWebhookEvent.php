<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    public $timestamps = false;

    protected $table = 'payment_webhook_events';

    protected $guarded = ['id'];

    protected $casts = ['payload_redacted' => 'array', 'signature_valid' => 'boolean', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
}
