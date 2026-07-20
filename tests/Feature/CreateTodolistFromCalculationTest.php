<?php

namespace Tests\Feature;

use App\Actions\CreateTodolistFromCalculation;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\Company;
use App\Models\Project;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTodolistFromCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function action(): CreateTodolistFromCalculation
    {
        return app(CreateTodolistFromCalculation::class);
    }

    public function test_it_copies_selected_items_into_todos(): void
    {
        $calculation = Calculation::factory()->create();
        $first = CalculationItem::factory()->forCalculation($calculation)
            ->create(['name' => 'Analýza', 'days' => 3, 'sort_order' => 0]);
        $second = CalculationItem::factory()->forCalculation($calculation)
            ->create(['name' => 'Vývoj', 'days' => 10, 'sort_order' => 1]);

        $project = Project::factory()->create();

        $todolist = $this->action()->handle($calculation, $project, [$first->id, $second->id]);

        $this->assertSame($project->id, $todolist->project_id);
        $this->assertSame($calculation->id, $todolist->calculation_id);
        $this->assertCount(2, $todolist->todos);

        $todo = $todolist->todos->firstWhere('name', 'Analýza');
        $this->assertSame(3, $todo->days);
        $this->assertSame($first->id, $todo->calculation_item_id);
    }

    public function test_it_preserves_the_item_hierarchy(): void
    {
        $calculation = Calculation::factory()->create();
        $parent = CalculationItem::factory()->forCalculation($calculation)->create(['sort_order' => 0]);
        $child = CalculationItem::factory()->childOf($parent)->create(['sort_order' => 1]);

        $todolist = $this->action()->handle(
            $calculation,
            Project::factory()->create(),
            [$parent->id, $child->id],
        );

        $parentTodo = $todolist->todos->firstWhere('calculation_item_id', $parent->id);
        $childTodo = $todolist->todos->firstWhere('calculation_item_id', $child->id);

        $this->assertNull($parentTodo->parent_id);
        $this->assertSame($parentTodo->id, $childTodo->parent_id);
    }

    public function test_it_pulls_in_ancestors_of_a_selected_child(): void
    {
        $calculation = Calculation::factory()->create();
        $grandparent = CalculationItem::factory()->forCalculation($calculation)->create(['sort_order' => 0]);
        $parent = CalculationItem::factory()->childOf($grandparent)->create(['sort_order' => 1]);
        $child = CalculationItem::factory()->childOf($parent)->create(['sort_order' => 2]);

        // Only the deepest item is selected — the chain above it must come along.
        $todolist = $this->action()->handle($calculation, Project::factory()->create(), [$child->id]);

        $this->assertCount(3, $todolist->todos);

        $childTodo = $todolist->todos->firstWhere('calculation_item_id', $child->id);
        $parentTodo = $todolist->todos->firstWhere('calculation_item_id', $parent->id);
        $grandparentTodo = $todolist->todos->firstWhere('calculation_item_id', $grandparent->id);

        $this->assertSame($parentTodo->id, $childTodo->parent_id);
        $this->assertSame($grandparentTodo->id, $parentTodo->parent_id);
        $this->assertNull($grandparentTodo->parent_id);
    }

    public function test_it_ignores_items_that_were_not_selected(): void
    {
        $calculation = Calculation::factory()->create();
        $wanted = CalculationItem::factory()->forCalculation($calculation)->create(['name' => 'Chceme']);
        CalculationItem::factory()->forCalculation($calculation)->create(['name' => 'Nechceme']);

        $todolist = $this->action()->handle($calculation, Project::factory()->create(), [$wanted->id]);

        $this->assertCount(1, $todolist->todos);
        $this->assertSame('Chceme', $todolist->todos->first()->name);
    }

    public function test_null_selection_falls_back_to_accepted_items(): void
    {
        $calculation = Calculation::factory()->confirmed()->create();
        $accepted = CalculationItem::factory()->forCalculation($calculation)->accepted()->create();
        CalculationItem::factory()->forCalculation($calculation)->create();

        $todolist = $this->action()->handle($calculation, Project::factory()->create(), null);

        $this->assertCount(1, $todolist->todos);
        $this->assertSame($accepted->id, $todolist->todos->first()->calculation_item_id);
    }

    public function test_it_names_the_list_after_the_customer_company_by_default(): void
    {
        $company = Company::factory()->create(['name' => 'Fondly s.r.o.']);
        $calculation = Calculation::factory()->forCompany($company)->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $todolist = $this->action()->handle($calculation, Project::factory()->create(), null);

        $this->assertSame('Fondly s.r.o.', $todolist->name);
    }

    public function test_an_explicit_name_wins(): void
    {
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $todolist = $this->action()->handle($calculation, Project::factory()->create(), null, 'Etapa 1');

        $this->assertSame('Etapa 1', $todolist->name);
    }

    public function test_deleting_a_todolist_removes_its_todos(): void
    {
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $todolist = $this->action()->handle($calculation, Project::factory()->create(), null);
        $todoId = $todolist->todos->first()->id;

        $todolist->delete();

        $this->assertNull(Todo::find($todoId));
    }

    public function test_deleting_the_source_calculation_keeps_the_todos(): void
    {
        $calculation = Calculation::factory()->create();
        CalculationItem::factory()->forCalculation($calculation)->accepted()->create();

        $todolist = $this->action()->handle($calculation, Project::factory()->create(), null);

        $calculation->delete();

        $todolist->refresh()->load('todos');
        $this->assertCount(1, $todolist->todos);
        $this->assertNull($todolist->calculation_id);
        $this->assertNull($todolist->todos->first()->calculation_item_id);
    }
}
