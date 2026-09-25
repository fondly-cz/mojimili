<?php

namespace App\Models;

use Database\Factories\CalculationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Calculation extends Model
{
    /** @use HasFactory<CalculationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'show_vat' => 'boolean',
    ];

    protected $appends = ['public_url'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($calculation) {
            $calculation->access_token = Str::random(32);
        });
    }

    public function getPublicUrlAttribute()
    {
        return url('/c/'.$this->access_token);
    }

    /**
     * The description is rendered as HTML. Plain text or Markdown (e.g. from the MCP tools)
     * is converted so its paragraphs and line breaks survive; HTML from the editor is kept.
     */
    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = self::descriptionToHtml($value);
    }

    public static function descriptionToHtml(?string $value): ?string
    {
        if ($value === null || trim($value) === '' || $value !== strip_tags($value)) {
            return $value;
        }

        return trim(Str::markdown($value, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]));
    }

    /**
     * @return HasMany<CalculationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CalculationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CalculationView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(CalculationView::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
