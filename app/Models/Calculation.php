<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Calculation extends Model
{
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
     * @return HasMany<CalculationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CalculationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
