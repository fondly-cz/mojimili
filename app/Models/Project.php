<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    protected static function booted(): void
    {
        // Todos cascade in the database, so their thread attachments are removed here.
        static::deleting(function (Project $project) {
            TodoComment::purgeFilesFor($project->todos()->pluck('todos.id'));
        });
    }

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

    /**
     * People with their own hourly rate in this project.
     *
     * @return BelongsToMany<User, $this>
     */
    public function userRates(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user_rates')
            ->withPivot('hourly_rate')
            ->withTimestamps();
    }

    /**
     * Rate for a new work report: the person's rate in this project,
     * then the project's default, then zero.
     */
    public function rateFor(?int $userId): string
    {
        $userRate = $userId === null
            ? null
            : $this->userRates()->whereKey($userId)->first()?->pivot->hourly_rate;

        // Pivot values are not cast, so normalise to the same "1234.50" shape as the decimal casts.
        return number_format((float) ($userRate ?? $this->hourly_rate ?? 0), 2, '.', '');
    }
}
