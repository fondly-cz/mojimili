<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInvitation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'all_todolists' => 'boolean',
        'todolist_ids' => 'array',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Put a freshly signed-up person into the projects they were invited to.
     */
    public static function acceptFor(User $user): void
    {
        $invitations = self::whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->get();

        foreach ($invitations as $invitation) {
            $invitation->project->grantAccess($user, $invitation->all_todolists, $invitation->todolist_ids ?? []);
            $invitation->delete();
        }
    }
}
