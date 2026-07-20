<?php

namespace App\Models;

use App\Enums\PaymentPeriod;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'cost' => 'decimal:2',
        'margin' => 'decimal:2',
        'payment_period' => PaymentPeriod::class,
    ];

    public function getPriceAttribute(): float
    {
        return $this->cost * (1 + $this->margin / 100);
    }
}
