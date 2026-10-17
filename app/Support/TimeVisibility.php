<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Todo;
use App\Models\User;

/**
 * What the roles outside administration may see of reported time and prices: a customer none of it,
 * a collaborator only their own entries plus the total spent on each todo (without who spent it).
 */
class TimeVisibility
{
    /**
     * Cut the loaded work reports of the todos down to what the user may see.
     *
     * @param  iterable<Todo>  $todos
     */
    public static function applyToTodos(iterable $todos, User $user): void
    {
        if ($user->isStaff()) {
            return;
        }

        foreach ($todos as $todo) {
            if (! $todo->relationLoaded('workReports')) {
                continue;
            }

            $all = $todo->workReports;
            $todo->setAttribute('spent_minutes', $user->isCollaborator() ? (int) $all->sum('minutes') : null);

            $own = $user->isCollaborator() ? $all->where('user_id', $user->id) : collect();

            $todo->setRelation('workReports', $own->each(function ($report) {
                $report->unsetRelation('invoice');
                $report->makeHidden(['hourly_rate', 'amount', 'invoice_id']);
            })->values());
        }
    }

    /**
     * Rates and budgets are not for the eyes of customers and collaborators.
     */
    public static function applyToProject(Project $project, User $user): void
    {
        if ($user->isStaff()) {
            return;
        }

        $project->makeHidden(['hourly_rate', 'budget', 'budget_minutes']);
        $project->unsetRelation('userRates');
    }
}
