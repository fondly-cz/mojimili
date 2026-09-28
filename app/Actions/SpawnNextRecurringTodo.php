<?php

namespace App\Actions;

use App\Models\Todo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates the next occurrence of a recurring todo once the current one is completed (Freelo-style).
 */
class SpawnNextRecurringTodo
{
    /** The copy is a new todo, so it keeps neither the completion nor the Freelo origin. */
    private const NOT_COPIED = ['is_done', 'completed_at', 'completed_by_user_id', 'freelo_id'];

    public function handle(Todo $todo): ?Todo
    {
        if (! $todo->recurrence_frequency || ! $todo->is_done || $todo->recurrence_remaining === 0) {
            return null;
        }

        // Unchecking and re-checking a todo must not spawn a second copy.
        if (Todo::where('recurrence_previous_id', $todo->id)->exists()) {
            return null;
        }

        $nextDate = $this->nextDate($todo);

        if ($todo->recurrence_ends_on && $nextDate->gt($todo->recurrence_ends_on)) {
            return null;
        }

        return DB::transaction(function () use ($todo, $nextDate) {
            $shiftDays = $todo->due_date
                ? (int) CarbonImmutable::parse($todo->due_date)->diffInDays($nextDate)
                : 0;

            $next = $todo->replicate(self::NOT_COPIED);
            $next->fill([
                'is_done' => false,
                'completed_at' => null,
                'completed_by_user_id' => null,
                'due_date' => $nextDate,
                'description' => $todo->recurrence_copy_description ? $todo->description : null,
                'recurrence_remaining' => $todo->recurrence_remaining === null ? null : $todo->recurrence_remaining - 1,
                'recurrence_previous_id' => $todo->id,
            ]);
            $next->save();
            $next->labels()->sync($todo->labels()->pluck('labels.id'));

            $this->copyChildren($todo, $next, $shiftDays);

            return $next;
        });
    }

    /**
     * First occurrence after both the current due date and today, so missed periods are skipped.
     */
    public function nextDate(Todo $todo): CarbonImmutable
    {
        $today = CarbonImmutable::today();
        $date = $todo->due_date ? CarbonImmutable::parse($todo->due_date) : $today;

        do {
            $date = $todo->recurrence_frequency->advance(
                $date,
                max(1, $todo->recurrence_interval),
                $todo->recurrence_working_days_only,
            );
        } while ($date->lte($today));

        return $date;
    }

    private function copyChildren(Todo $source, Todo $target, int $shiftDays): void
    {
        foreach ($source->children()->get() as $child) {
            $copy = $child->replicate(self::NOT_COPIED);
            $copy->fill([
                'parent_id' => $target->id,
                'is_done' => false,
                'completed_at' => null,
                'completed_by_user_id' => null,
                'due_date' => $child->due_date ? CarbonImmutable::parse($child->due_date)->addDays($shiftDays) : null,
                'recurrence_frequency' => null,
                'recurrence_previous_id' => null,
            ]);
            $copy->save();
            $copy->labels()->sync($child->labels()->pluck('labels.id'));

            $this->copyChildren($child, $copy, $shiftDays);
        }
    }
}
