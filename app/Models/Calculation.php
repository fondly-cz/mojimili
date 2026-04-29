<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CalculationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
