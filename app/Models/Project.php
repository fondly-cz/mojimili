<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
    ];

    /**
     * @return HasMany<Todolist, $this>
     */
    public function todolists(): HasMany
    {
        return $this->hasMany(Todolist::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<CompanyEmployee, $this>
     */
    public function companyEmployee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasManyThrough<Todo, Todolist, $this>
     */
    public function todos(): HasManyThrough
    {
        return $this->hasManyThrough(Todo::class, Todolist::class);
    }
}
