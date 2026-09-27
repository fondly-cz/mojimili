<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkReportInvoicingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    private function todoInProject(Project $project): Todo
    {
        $todolist = Todolist::factory()->create(['project_id' => $project->id]);

        return Todo::factory()->forTodolist($todolist)->create();
    }

    public function test_a_work_report_uses_the_project_rate_by_default(): void
    {
        $user = $this->manager();
        $todo = $this->todoInProject(Project::factory()->create(['hourly_rate' => 1200]));

        $this->actingAs($user)
            ->post("/todos/{$todo->id}/work-reports", [
                'date' => '2026-09-20',
                'minutes' => 90,
            ])
            ->assertSessionHasNoErrors();

        $report = WorkReport::sole();
        $this->assertSame('1200.00', $report->hourly_rate);
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame(1800.0, $report->amount);
    }

    public function test_a_work_report_can_have_its_own_rate(): void
    {
        $todo = $this->todoInProject(Project::factory()->create(['hourly_rate' => 1200]));

        $this->actingAs($this->manager())
            ->post("/todos/{$todo->id}/work-reports", [
                'date' => '2026-09-20',
                'minutes' => 30,
                'hourly_rate' => 2000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2000.00', WorkReport::sole()->hourly_rate);
    }

    public function test_changing_the_project_rate_keeps_existing_reports(): void
    {
        $project = Project::factory()->create(['hourly_rate' => 1000]);
        $report = WorkReport::factory()->forTodo($this->todoInProject($project))->create(['hourly_rate' => 1000]);

        $this->actingAs($this->manager())
            ->put("/projects/{$project->id}", ['name' => $project->name, 'hourly_rate' => 1500])
            ->assertSessionHasNoErrors();

        $this->assertSame('1500.00', $project->fresh()->hourly_rate);
        $this->assertSame('1000.00', $report->fresh()->hourly_rate);
    }

    public function test_reports_from_several_projects_go_on_one_invoice(): void
    {
        $first = WorkReport::factory()->forTodo($this->todoInProject(Project::factory()->create()))->create();
        $second = WorkReport::factory()->forTodo($this->todoInProject(Project::factory()->create()))->create();

        $this->actingAs($this->manager())
            ->post('/invoices', [
                'number' => '2026-09-karel',
                'url' => 'https://app.fakturoid.cz/invoices/1',
                'issued_at' => '2026-09-27',
                'work_report_ids' => [$first->id, $second->id],
            ])
            ->assertSessionHasNoErrors();

        $invoice = Invoice::sole();
        $this->assertSame($invoice->id, $first->fresh()->invoice_id);
        $this->assertSame($invoice->id, $second->fresh()->invoice_id);
        $this->assertSame('https://app.fakturoid.cz/invoices/1', $invoice->url);
    }

    public function test_a_report_cannot_be_invoiced_twice(): void
    {
        $existing = Invoice::factory()->create();
        $report = WorkReport::factory()->invoicedIn($existing)->create();

        $this->actingAs($this->manager())
            ->post('/invoices', [
                'number' => 'druha',
                'work_report_ids' => [$report->id],
            ])
            ->assertSessionHasErrors('work_report_ids');

        $this->assertSame(1, Invoice::count());
        $this->assertSame($existing->id, $report->fresh()->invoice_id);
    }

    public function test_reports_can_be_attached_to_an_existing_invoice(): void
    {
        $invoice = Invoice::factory()->create();
        $report = WorkReport::factory()->create();

        $this->actingAs($this->manager())
            ->post("/invoices/{$invoice->id}/attach", ['work_report_ids' => [$report->id]])
            ->assertRedirect("/invoices/{$invoice->id}");

        $this->assertSame($invoice->id, $report->fresh()->invoice_id);
    }

    public function test_an_invoiced_report_is_locked(): void
    {
        $report = WorkReport::factory()->invoicedIn(Invoice::factory()->create())->create(['minutes' => 60]);

        $this->actingAs($this->manager())
            ->patch("/work-reports/{$report->id}", ['minutes' => 120])
            ->assertSessionHasErrors('work_report');

        $this->actingAs($this->manager())
            ->delete("/work-reports/{$report->id}")
            ->assertSessionHasErrors('work_report');

        $this->assertSame(60, $report->fresh()->minutes);
    }

    public function test_detaching_marks_a_report_uninvoiced_again(): void
    {
        $invoice = Invoice::factory()->create();
        $report = WorkReport::factory()->invoicedIn($invoice)->create();

        $this->actingAs($this->manager())
            ->post("/invoices/{$invoice->id}/detach", ['work_report_ids' => [$report->id]])
            ->assertSessionHasNoErrors();

        $this->assertNull($report->fresh()->invoice_id);
    }

    public function test_deleting_an_invoice_releases_its_reports(): void
    {
        $invoice = Invoice::factory()->create();
        $report = WorkReport::factory()->invoicedIn($invoice)->create();

        $this->actingAs($this->manager())
            ->delete("/invoices/{$invoice->id}")
            ->assertRedirect('/invoices');

        $this->assertModelMissing($invoice);
        $this->assertNull($report->fresh()->invoice_id);
    }

    public function test_billing_lists_only_uninvoiced_reports(): void
    {
        $open = WorkReport::factory()->create();
        WorkReport::factory()->invoicedIn(Invoice::factory()->create())->create();

        $this->actingAs($this->manager())
            ->get('/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Index')
                ->has('reports', 1)
                ->where('reports.0.id', $open->id)
            );
    }

    public function test_invoices_index_shows_totals(): void
    {
        $invoice = Invoice::factory()->create();
        WorkReport::factory()->invoicedIn($invoice)->create(['minutes' => 90, 'hourly_rate' => 1000]);
        WorkReport::factory()->invoicedIn($invoice)->create(['minutes' => 30, 'hourly_rate' => 2000]);

        $this->actingAs($this->manager())
            ->get('/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invoices/Index')
                ->where('invoices.data.0.work_reports_count', 2)
                ->where('invoices.data.0.total_minutes', 120)
                ->where('invoices.data.0.total_amount', fn ($amount) => (float) $amount === 2500.0)
            );
    }

    public function test_invoice_detail_renders(): void
    {
        $invoice = Invoice::factory()->create();
        WorkReport::factory()->invoicedIn($invoice)->create();

        $this->actingAs($this->manager())
            ->get("/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invoices/Show')
                ->has('invoice.work_reports', 1)
            );
    }
}
