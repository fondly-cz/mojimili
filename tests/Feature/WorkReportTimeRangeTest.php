<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateWorkReportTool;
use App\Mcp\Tools\UpdateWorkReportTool;
use App\Models\Todo;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkReportTimeRangeTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_a_range_derives_date_and_minutes(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->post("/todos/{$todo->id}/work-reports", [
                'date' => '2026-09-01',
                'minutes' => 5,
                'started_at' => '2026-09-02 08:15',
                'ended_at' => '2026-09-02 10:00',
            ])
            ->assertSessionHasNoErrors();

        $report = $todo->workReports()->firstOrFail();
        $this->assertSame(105, $report->minutes);
        $this->assertSame('2026-09-02', $report->date->toDateString());
        $this->assertSame('2026-09-02 08:15', $report->started_at->format('Y-m-d H:i'));
    }

    public function test_a_range_can_cross_midnight(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->post("/todos/{$todo->id}/work-reports", [
                'started_at' => '2026-09-02 23:30',
                'ended_at' => '2026-09-03 00:45',
            ])
            ->assertSessionHasNoErrors();

        $report = $todo->workReports()->firstOrFail();
        $this->assertSame(75, $report->minutes);
        $this->assertSame('2026-09-02', $report->date->toDateString());
    }

    public function test_a_range_must_end_after_it_starts_and_last_at_most_a_day(): void
    {
        $todo = Todo::factory()->create();
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post("/todos/{$todo->id}/work-reports", ['started_at' => '2026-09-02 10:00', 'ended_at' => '2026-09-02 09:00'])
            ->assertSessionHasErrors('ended_at');

        $this->actingAs($manager)
            ->post("/todos/{$todo->id}/work-reports", ['started_at' => '2026-09-02 10:00', 'ended_at' => '2026-09-03 11:00'])
            ->assertSessionHasErrors('ended_at');

        $this->actingAs($manager)
            ->post("/todos/{$todo->id}/work-reports", ['date' => '2026-09-02', 'minutes' => 30, 'started_at' => '2026-09-02 10:00'])
            ->assertSessionHasErrors('ended_at');

        $this->assertSame(0, $todo->workReports()->count());
    }

    public function test_changing_the_range_recalculates_minutes(): void
    {
        $report = WorkReport::factory()->create(['started_at' => '2026-09-02 08:00', 'ended_at' => '2026-09-02 09:00']);
        $this->assertSame(60, $report->minutes);

        $this->actingAs($this->manager())
            ->patch("/work-reports/{$report->id}", [
                'date' => '2026-09-02',
                'minutes' => 60,
                'started_at' => '2026-09-02 08:00',
                'ended_at' => '2026-09-02 11:30',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(210, $report->fresh()->minutes);
    }

    public function test_changing_only_minutes_moves_the_end(): void
    {
        $report = WorkReport::factory()->create(['started_at' => '2026-09-02 08:00', 'ended_at' => '2026-09-02 09:00']);

        $report->update(['minutes' => 90]);

        $this->assertSame('2026-09-02 09:30', $report->fresh()->ended_at->format('Y-m-d H:i'));
    }

    public function test_changing_only_the_date_moves_the_range(): void
    {
        $report = WorkReport::factory()->create(['started_at' => '2026-09-02 08:00', 'ended_at' => '2026-09-02 09:00']);

        $report->update(['date' => '2026-09-05']);

        $fresh = $report->fresh();
        $this->assertSame('2026-09-05 08:00', $fresh->started_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-05 09:00', $fresh->ended_at->format('Y-m-d H:i'));
        $this->assertSame(60, $fresh->minutes);
    }

    public function test_the_range_can_be_cleared(): void
    {
        $report = WorkReport::factory()->create(['started_at' => '2026-09-02 08:00', 'ended_at' => '2026-09-02 09:00']);

        $this->actingAs($this->manager())
            ->patch("/work-reports/{$report->id}", ['minutes' => 45, 'started_at' => null, 'ended_at' => null])
            ->assertSessionHasNoErrors();

        $fresh = $report->fresh();
        $this->assertNull($fresh->started_at);
        $this->assertSame(45, $fresh->minutes);
    }

    public function test_mcp_logs_and_updates_a_range(): void
    {
        $todo = Todo::factory()->create();
        $manager = $this->manager();

        CrmServer::actingAs($manager)->tool(CreateWorkReportTool::class, [
            'todo_id' => $todo->id,
            'started_at' => '2026-09-02 13:00',
            'ended_at' => '2026-09-02 14:15',
        ])->assertOk()->assertSee('75 min');

        $report = $todo->workReports()->firstOrFail();

        CrmServer::actingAs($manager)->tool(UpdateWorkReportTool::class, [
            'id' => $report->id,
            'minutes' => 30,
        ])->assertOk()->assertSee('"ended_at": "2026-09-02 13:30"');
    }
}
