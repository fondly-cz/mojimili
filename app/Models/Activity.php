<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of the change history: who did what, and to which record.
 *
 * @property array<string, mixed>|null $properties
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
    ];

    protected $appends = ['text'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Entries of projects and todolists the user may open; administrators see everything,
     * including changes that belong to no project.
     *
     * @param  Builder<Activity>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        // Reported time is for staff; a collaborator sees their own entries.
        if (! $user->isStaff()) {
            $query->where(fn (Builder $entries) => $entries
                ->where('activities.event', 'not like', 'work_report.%')
                ->when($user->isCollaborator(), fn (Builder $q) => $q->orWhere('activities.user_id', $user->id)));
        }

        $query->whereIn('activities.project_id', Project::visibleTo($user)->select('projects.id'))
            ->where(fn (Builder $lists) => $lists
                ->whereNull('activities.todolist_id')
                ->orWhereIn('activities.todolist_id', Todolist::visibleTo($user)->select('todolists.id')));
    }

    public function getTextAttribute(): string
    {
        return ActivityText::describe($this);
    }
}
