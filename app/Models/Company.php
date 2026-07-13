<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ico',
        'dic',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'industry',
        'employee_count',
        'annual_revenue',
        'notes',
        'status',
    ];

    protected $casts = [
        'annual_revenue' => 'decimal:2',
        'employee_count' => 'integer',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeProspect($query)
    {
        return $query->where('status', 'prospect');
    }

    /**
     * @return HasMany<CompanyEmployee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(CompanyEmployee::class);
    }
}
