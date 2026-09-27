<?php

namespace Tests\Feature;

use App\Enums\RecurrenceFrequency;
use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\UpdateTodoTool;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTodoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday.
        $this->travelTo('2026-09-30 10:00:00');
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    private function complete(Todo $todo): void
    {
        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", ['is_done' => true])
            ->assertRedirect();
    }

    private function nextOf(Todo $todo): ?Todo
    {
        return Todo::where('recurrence_previous_id', $todo->id)->first();
    }

    public function test_it_sets_recurrence_and_anchors_it_on_today_without_a_due_date(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->patch("/todos/{$todo->id}", [
                'recurrence_frequency' => 'weekly',
                'recurrence_interval' => 2,
            ])
            ->assertSessionHasNoErrors();

        $todo->refresh();
        $this->assertSame(RecurrenceFrequency::WEEKLY, $todo->recurrence_frequency);
        $this->assertSame(2, $todo->recurrence_interval);
        $this->assertSame('2026-09-30', $todo->due_date->toDateString());
    }

    public function test_completing_a_recurring_todo_spawns_the_next_occurrence(): void
    {
        $user = User::factory()->create();
        $todo = Todo::factory()->assignedTo($user)->create([
            'name' => 'Záloha webu',
            'description' => 'Stáhnout DB',
            'due_date' => '2026-10-02',
            'recurrence_frequency' => 'weekly',
        ]);

        $this->complete($todo);

        $next = $this->nextOf($todo);
        $this->assertNotNull($next);
        $this->assertSame('Záloha webu', $next->name);
        $this->assertSame('Stáhnout DB', $next->description);
        $this->assertSame($user->id, $next->assigned_user_id);
        $this->assertSame($todo->todolist_id, $next->todolist_id);
        $this->assertFalse($next->is_done);
        $this->assertSame('2026-10-09', $next->due_date->toDateString());
        $this->assertSame(RecurrenceFrequency::WEEKLY, $next->recurrence_frequency);
    }

    public function test_a_late_completion_skips_missed_periods(): void
    {
        $todo = Todo::factory()->create([
            'due_date' => '2026-09-01',
            'recurrence_frequency' => 'weekly',
        ]);

        $this->complete($todo);

        // 1. 9. + n weeks, first date after today (30. 9.).
        $this->assertSame('2026-10-06', $this->nextOf($todo)->due_date->toDateString());
    }

    public function test_daily_working_days_only_skip_the_weekend(): void
    {
        $todo = Todo::factory()->create([
            'due_date' => '2026-10-02', // Friday
            'recurrence_frequency' => 'daily',
            'recurrence_working_days_only' => true,
        ]);

        $this->complete($todo);

        $this->assertSame('2026-10-05', $this->nextOf($todo)->due_date->toDateString());
    }

    public function test_monthly_recurrence_does_not_overflow(): void
    {
        $this->travelTo('2027-01-31 10:00:00');
        $todo = Todo::factory()->create([
            'due_date' => '2027-01-31',
            'recurrence_frequency' => 'monthly',
        ]);

        $this->complete($todo);

        $this->assertSame('2027-02-28', $this->nextOf($todo)->due_date->toDateString());
    }

    public function test_recompleting_does_not_spawn_twice(): void
    {
        $todo = Todo::factory()->create(['due_date' => '2026-10-01', 'recurrence_frequency' => 'daily']);

        $this->complete($todo);
        $this->actingAs($this->manager())->patch("/todos/{$todo->id}", ['is_done' => false]);
        $this->complete($todo);

        $this->assertSame(1, Todo::where('recurrence_previous_id', $todo->id)->count());
    }

    public function test_count_limit_stops_the_recurrence(): void
    {
        $todo = Todo::factory()->create([
            'due_date' => '2026-10-01',
            'recurrence_frequency' => 'daily',
            'recurrence_remaining' => 1,
        ]);

        $this->complete($todo);
        $next = $this->nextOf($todo);
        $this->assertSame(0, $next->recurrence_remaining);

        $this->complete($next);
        $this->assertNull($this->nextOf($next));
    }

    public function test_end_date_stops_the_recurrence(): void
    {
        $todo = Todo::factory()->create([
            'due_date' => '2026-10-01',
            'recurrence_frequency' => 'weekly',
            'recurrence_ends_on' => '2026-10-05',
        ]);

        $this->complete($todo);

        $this->assertNull($this->nextOf($todo));
    }

    public function test_subtasks_are_copied_open_with_shifted_due_dates(): void
    {
        $todo = Todo::factory()->create(['due_date' => '2026-10-01', 'recurrence_frequency' => 'monthly']);
        $child = Todo::factory()->childOf($todo)->done()->create(['name' => 'Kontrola', 'due_date' => '2026-09-29']);
        Todo::factory()->childOf($child)->done()->create(['name' => 'Detail']);

        $this->complete($todo);

        $next = $this->nextOf($todo);
        $copiedChild = $next->children()->firstOrFail();
        $this->assertSame('Kontrola', $copiedChild->name);
        $this->assertFalse($copiedChild->is_done);
        $this->assertNull($copiedChild->recurrence_frequency);
        $this->assertSame('2026-10-30', $copiedChild->due_date->toDateString());
        $this->assertSame('Detail', $copiedChild->children()->firstOrFail()->name);
    }

    public function test_description_is_not_copied_when_disabled(): void
    {
        $todo = Todo::factory()->create([
            'description' => 'Jen jednou',
            'due_date' => '2026-10-01',
            'recurrence_frequency' => 'weekly',
            'recurrence_copy_description' => false,
        ]);

        $this->complete($todo);

        $this->assertNull($this->nextOf($todo)->description);
    }

    public function test_recurrence_is_refused_under_a_recurring_parent(): void
    {
        $parent = Todo::factory()->create(['due_date' => '2026-10-01', 'recurrence_frequency' => 'weekly']);
        $child = Todo::factory()->childOf($parent)->create();

        $this->actingAs($this->manager())
            ->patch("/todos/{$child->id}", ['recurrence_frequency' => 'daily'])
            ->assertSessionHasErrors('recurrence_frequency');

        $this->assertNull($child->fresh()->recurrence_frequency);
    }

    public function test_non_recurring_todo_does_not_spawn(): void
    {
        $todo = Todo::factory()->create();

        $this->complete($todo);

        $this->assertSame(1, Todo::count());
    }

    public function test_mcp_tool_sets_recurrence_and_reports_the_next_occurrence(): void
    {
        $todo = Todo::factory()->create(['due_date' => '2026-10-01']);
        $manager = $this->manager();

        CrmServer::actingAs($manager)->tool(UpdateTodoTool::class, [
            'id' => $todo->id,
            'recurrence_frequency' => 'monthly',
        ])->assertOk()->assertSee('Opakování: Měsíčně');

        CrmServer::actingAs($manager)->tool(UpdateTodoTool::class, [
            'id' => $todo->id,
            'is_done' => true,
        ])->assertOk()->assertSee('termín 2026-11-01');
    }
}
