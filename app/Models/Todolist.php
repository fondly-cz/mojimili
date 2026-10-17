<?php

namespace App\Models;

use App\Models\Contracts\ProjectRestricted;
use Database\Factories\TodolistFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Todolist extends Model implements ProjectRestricted
{
    /** @use HasFactory<TodolistFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Todos cascade in the database, so their thread attachments are removed here.
        static::deleting(function (Todolist $todolist) {
            TodoComment::purgeFilesFor($todolist->todos()->pluck('id'));
        });
    }

    /**
     * @return HasMany<Todo, $this>
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Calculation, $this>
     */
    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    /**
     * Todolists the user can open: administrators see all of them, a project member either
     * all of the project's lists or only the ones handed to them.
     *
     * @param  Builder<Todolist>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $membership = DB::table('project_members')->select('project_id')->where('user_id', $user->id);

        $query->whereIn('todolists.project_id', $membership)
            ->where(fn (Builder $lists) => $lists
                ->whereIn('todolists.project_id', (clone $membership)->where('all_todolists', true))
                ->orWhereIn('todolists.id', DB::table('todolist_user')->select('todolist_id')->where('user_id', $user->id)));
    }

    public function isVisibleTo(?User $user): bool
    {
        return $user !== null && self::visibleTo($user)->whereKey($this->id)->exists();
    }

    /**
     * A list someone creates stays visible to them even when they see only some lists of the project.
     */
    public function shareWith(?User $user): void
    {
        if ($user === null || $user->isAdmin()) {
            return;
        }

        DB::table('todolist_user')->insertOrIgnore([
            'todolist_id' => $this->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
