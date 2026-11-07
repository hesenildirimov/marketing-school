<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['paid_at' => 'datetime', 'save_card' => 'boolean'];

    /** Status maşını yalnız irəli gedir (ardıcıllıqdan kənar webhook geri salmır). */
    public const RANK = ['created' => 0, 'pending' => 1, 'failed' => 2, 'paid' => 3, 'refunded' => 4, 'charged_back' => 4];

    public function canMoveTo(string $status): bool
    {
        return (self::RANK[$status] ?? -1) > (self::RANK[$this->status] ?? 0);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
