<?php

namespace App\Models;

use App\Enums\PaymentPeriod;
use Database\Factories\CalculationItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationItem extends Model
{
    /** @use HasFactory<CalculationItemFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_accepted' => 'boolean',
        'is_required' => 'boolean',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'margin' => 'decimal:2',
        'parent_id' => 'integer',
        'payment_period' => PaymentPeriod::class,
    ];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function parent()
    {
        return $this->belongsTo(CalculationItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(CalculationItem::class, 'parent_id');
    }
}
