<?php

namespace App\Models;

use App\Models\Contracts\ProjectRestricted;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class Project extends Model implements ProjectRestricted
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'budget' => 'decimal:2',
        'budget_minutes' => 'integer',
        'due_date' => 'date:Y-m-d',
    ];

    /**
     * Deadline and budget, shared by the web UI and the MCP tools.
     *
     * @return array<string, string>
     */
    public static function planningRules(): array
    {
        return [
            'due_date' => 'sometimes|nullable|date',
            'budget' => 'sometimes|nullable|numeric|min:0|max:9999999999',
            'budget_minutes' => 'sometimes|nullable|integer|min:0|max:100000000',
        ];
    }

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
     * @return HasMany<ProjectDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->orderBy('sort_order')->orderBy('id');
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
     * People assigned to the project; only they can be mentioned in its todos.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withTimestamps()
            ->orderBy('users.name');
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

    /**
     * Projects the user is assigned to; administrators see all of them.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereIn('projects.id', DB::table('project_members')->select('project_id')->where('user_id', $user->id));
    }

    public function isVisibleTo(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->members()->whereKey($user->id)->exists());
    }

    /**
     * A todo can be assigned only to a person from the project.
     */
    public function memberRule(): Exists
    {
        return Rule::exists('project_members', 'user_id')->where('project_id', $this->id);
    }
}
