<?php

namespace App\Models;

use App\Models\Contracts\ProjectRestricted;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model implements ProjectRestricted
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'date',
    ];

    /**
     * @return HasMany<WorkReport, $this>
     */
    public function workReports(): HasMany
    {
        return $this->hasMany(WorkReport::class)->orderBy('date')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Adds work_reports_count, total_minutes and total_amount (CZK excl. VAT).
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeWithTotals(Builder $query): void
    {
        $query->withCount('workReports')
            ->withSum('workReports as total_minutes', 'minutes')
            ->addSelect(['total_amount' => WorkReport::selectRaw('coalesce(sum(minutes * hourly_rate / 60), 0)')
                ->whereColumn('invoice_id', 'invoices.id')]);
    }

    /**
     * An invoice is hidden as soon as it bills work from a project the user is not assigned to.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereDoesntHave('workReports', fn ($reports) => $reports->whereNotIn(
            'work_reports.id',
            WorkReport::visibleTo($user)->select('work_reports.id'),
        ));
    }

    public function isVisibleTo(?User $user): bool
    {
        return $user !== null && self::visibleTo($user)->whereKey($this->id)->exists();
    }
}
