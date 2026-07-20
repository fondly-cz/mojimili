<?php

namespace Tests\Feature;

use App\Actions\CreateTodolistFromCalculation;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneDuplicateCalculationItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_orphan_duplicates_but_keeps_real_ones(): void
    {
        $hosting = Service::factory()->create();
        $domain = Service::factory()->create();
        $calc = Calculation::factory()->create();

        // Orphan duplicate root (no children) + real parent (has child) + solo item.
        $orphan = CalculationItem::factory()->forCalculation($calc)
            ->create(['name' => 'Hosting', 'service_id' => $hosting->id, 'sort_order' => 0, 'is_accepted' => true]);
        $realParent = CalculationItem::factory()->forCalculation($calc)
            ->create(['name' => 'Hosting', 'service_id' => $hosting->id, 'sort_order' => 1, 'is_accepted' => true]);
        CalculationItem::factory()->childOf($realParent)
            ->create(['name' => 'Zaloha', 'sort_order' => 2, 'is_accepted' => true]);
        $solo = CalculationItem::factory()->forCalculation($calc)
            ->create(['name' => 'Domena', 'service_id' => $domain->id, 'sort_order' => 3, 'is_accepted' => true]);

        $list = app(CreateTodolistFromCalculation::class)->handle($calc, Project::factory()->create());
        $this->assertCount(4, $list->todos); // mirrors the corruption

        $this->artisan('calculations:prune-duplicate-items --force')->assertSuccessful();

        // Orphan item + its todo gone; real parent, child, solo untouched.
        $this->assertDatabaseMissing('calculation_items', ['id' => $orphan->id]);
        $this->assertDatabaseHas('calculation_items', ['id' => $realParent->id]);
        $this->assertDatabaseHas('calculation_items', ['id' => $solo->id]);
        $this->assertCount(3, $list->fresh()->todos);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $hosting = Service::factory()->create();
        $calc = Calculation::factory()->create();
        $orphan = CalculationItem::factory()->forCalculation($calc)->create(['name' => 'H', 'service_id' => $hosting->id]);
        $real = CalculationItem::factory()->forCalculation($calc)->create(['name' => 'H', 'service_id' => $hosting->id]);
        CalculationItem::factory()->childOf($real)->create(['name' => 'C']);

        $this->artisan('calculations:prune-duplicate-items')->assertSuccessful();

        $this->assertDatabaseHas('calculation_items', ['id' => $orphan->id]);
    }
}
