<?php

namespace App\Models;

use Closure;
use Database\Factories\WorkReportFactory;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
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
        // Wall-clock times as entered, serialised without a timezone shift.
        'started_at' => 'datetime:Y-m-d H:i',
        'ended_at' => 'datetime:Y-m-d H:i',
        'minutes' => 'integer',
        'hourly_rate' => 'decimal:2',
        'invoice_id' => 'integer',
    ];

    protected $appends = ['amount'];

    protected static function booted(): void
    {
        // A from–to range is the source of truth: date and minutes follow it. Changing only
        // the date moves the range to that day, changing only minutes moves its end.
        static::saving(function (WorkReport $report) {
            if (! $report->started_at || ! $report->ended_at) {
                return;
            }

            $rangeChanged = $report->isDirty(['started_at', 'ended_at']);

            if (! $rangeChanged && $report->isDirty('date')) {
                $days = (int) $report->started_at->copy()->startOfDay()->diffInDays($report->date->copy()->startOfDay(), false);
                $report->started_at = $report->started_at->copy()->addDays($days);
                $report->ended_at = $report->ended_at->copy()->addDays($days);
            }

            if (! $rangeChanged && $report->isDirty('minutes')) {
                $report->ended_at = $report->started_at->copy()->addMinutes($report->minutes);
            }

            $report->date = $report->started_at->toDateString();
            $report->minutes = (int) round($report->started_at->diffInMinutes($report->ended_at));
        });
    }

    /**
     * Validation rules shared by the web form and the MCP tools. Either minutes (with a date)
     * or a started_at–ended_at range is required; the range wins when both are sent.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $partial = false): array
    {
        return [
            'date' => $partial ? 'sometimes|required|date' : 'required_without:started_at|nullable|date',
            'minutes' => $partial ? 'sometimes|required|integer|min:1|max:1440' : 'required_without:started_at|nullable|integer|min:1|max:1440',
            'started_at' => 'nullable|date|required_with:ended_at',
            'ended_at' => [
                'nullable',
                'date',
                'required_with:started_at',
                'after:started_at',
                new class implements DataAwareRule, ValidationRule
                {
                    /** @var array<string, mixed> */
                    private array $data = [];

                    public function setData(array $data): static
                    {
                        $this->data = $data;

                        return $this;
                    }

                    public function validate(string $attribute, mixed $value, Closure $fail): void
                    {
                        $start = strtotime((string) ($this->data['started_at'] ?? ''));
                        $end = strtotime((string) $value);

                        if ($start && $end && $end - $start > 24 * 3600) {
                            $fail('Výkaz může trvat nejvýš 24 hodin.');
                        }
                    }
                },
            ],
            'hourly_rate' => 'nullable|numeric|min:0|max:99999999',
            'description' => 'nullable|string|max:2000',
            'user_id' => 'nullable|integer|exists:users,id',
        ];
    }

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
