<?php

namespace App\Console\Commands;

use App\Models\CalculationItem;
use App\Models\Todo;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class PruneDuplicateCalculationItems extends Command
{
    protected $signature = 'calculations:prune-duplicate-items {--force : Skutečně smazat (jinak jen výpis)}';

    protected $description = 'Najde a odstraní osiřelé duplicitní položky kalkulací (a z nich odvozené úkoly) vzniklé kolizí unique_id.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $itemVictims = $this->duplicateItems();
        $todoVictims = $this->duplicateTodos();

        $this->report('Položky kalkulací', $itemVictims->map(
            fn (CalculationItem $i) => "calc {$i->calculation_id} · item {$i->id} · {$i->name}"
        )->all());

        $this->report('Úkoly', $todoVictims->map(
            fn (Todo $t) => "list {$t->todolist_id} · todo {$t->id} · {$t->name}"
        )->all());

        if ($itemVictims->isEmpty() && $todoVictims->isEmpty()) {
            $this->info('Žádné duplikáty k odstranění.');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->warn('DRY-RUN: nic nebylo smazáno. Spusť s --force pro reálné odstranění.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($itemVictims, $todoVictims) {
            Todo::whereIn('id', $todoVictims->pluck('id'))->delete();
            CalculationItem::whereIn('id', $itemVictims->pluck('id'))->delete();
        });

        $this->info(sprintf(
            'Smazáno %d položek kalkulací a %d úkolů.',
            $itemVictims->count(),
            $todoVictims->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Duplicate = same (calculation_id, service_id, parent_id, name) as a sibling,
     * where exactly one of the twins actually has children. The childless twins are
     * the orphan duplicates created by a unique_id collision – those get pruned.
     *
     * @return SupportCollection<int, CalculationItem>
     */
    private function duplicateItems(): SupportCollection
    {
        $parentIds = CalculationItem::query()
            ->whereNotNull('parent_id')
            ->distinct()
            ->pluck('parent_id')
            ->all();

        return CalculationItem::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CalculationItem $i) => implode('|', [
                $i->calculation_id, $i->service_id, $i->parent_id ?? 'null', $i->name,
            ]))
            ->flatMap(function (Collection $group) use ($parentIds) {
                if ($group->count() < 2) {
                    return [];
                }

                [$withChildren, $childless] = $group->partition(
                    fn (CalculationItem $i) => in_array($i->id, $parentIds, true)
                );

                // Only prune when exactly one twin owns the children; otherwise it is
                // ambiguous (a legitimately repeated service) and we leave it alone.
                return $withChildren->count() === 1 ? $childless : [];
            })
            ->values();
    }

    /**
     * Same signature-based detection for the todos already generated from the
     * corrupted calculations: identical root todos where only one owns children.
     *
     * @return SupportCollection<int, Todo>
     */
    private function duplicateTodos(): SupportCollection
    {
        $parentIds = Todo::query()
            ->whereNotNull('parent_id')
            ->distinct()
            ->pluck('parent_id')
            ->all();

        return Todo::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Todo $t) => implode('|', [
                $t->todolist_id, $t->parent_id ?? 'null', $t->name,
            ]))
            ->flatMap(function (Collection $group) use ($parentIds) {
                if ($group->count() < 2) {
                    return [];
                }

                [$withChildren, $childless] = $group->partition(
                    fn (Todo $t) => in_array($t->id, $parentIds, true)
                );

                return $withChildren->count() === 1 ? $childless : [];
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function report(string $heading, array $lines): void
    {
        $this->line('<comment>'.$heading.'</comment> ('.count($lines).'):');

        if ($lines === []) {
            $this->line('  —');

            return;
        }

        foreach ($lines as $line) {
            $this->line("  • {$line}");
        }
    }
}
