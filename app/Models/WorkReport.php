<?php

namespace App\Models;

use Database\Factories\WorkReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkReport extends Model
{
    /** @use HasFactory<WorkReportFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'minutes' => 'integer',
        'hourly_rate' => 'decimal:2',
        'invoice_id' => 'integer',
    ];

    protected $appends = ['amount'];

    /**
     * @return BelongsTo<Todo, $this>
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Price of the report in CZK excl. VAT.
     */
    public function getAmountAttribute(): float
    {
        return round($this->minutes / 60 * (float) $this->hourly_rate, 2);
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * @param  Builder<WorkReport>  $query
     */
    public function scopeUninvoiced(Builder $query): void
    {
        $query->whereNull('invoice_id');
    }
}
