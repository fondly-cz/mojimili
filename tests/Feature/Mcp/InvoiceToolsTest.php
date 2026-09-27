<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateInvoiceTool;
use App\Mcp\Tools\DeleteInvoiceTool;
use App\Mcp\Tools\DeleteWorkReportTool;
use App\Mcp\Tools\GetInvoiceTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListInvoicesTool;
use App\Mcp\Tools\ListUninvoicedWorkReportsTool;
use App\Mcp\Tools\ListUsersTool;
use App\Mcp\Tools\UpdateInvoiceTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateWorkReportTool;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Todo;
use App\Models\Todolist;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceToolsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    private function todoInProject(Project $project): Todo
    {
        $todolist = Todolist::factory()->create(['project_id' => $project->id]);

        return Todo::factory()->forTodolist($todolist)->create();
    }

    public function test_it_lists_users(): void
    {
        User::factory()->create(['name' => 'Karel Novak']);

        $response = CrmServer::actingAs($this->manager())->tool(ListUsersTool::class, ['search' => 'Karel']);

        $response->assertOk()->assertSee('Karel Novak');
    }

    public function test_it_lists_uninvoiced_work_reports_with_filters(): void
    {
        $project = Project::factory()->create(['name' => 'Atlantis']);
        $other = Project::factory()->create(['name' => 'Jiny projekt']);
        WorkReport::factory()->forTodo($this->todoInProject($project))->create(['minutes' => 90, 'hourly_rate' => 1000, 'description' => 'Hledana prace']);
        WorkReport::factory()->forTodo($this->todoInProject($project))->invoicedIn(Invoice::factory()->create())->create(['description' => 'Uz vyfakturovano']);
        WorkReport::factory()->forTodo($this->todoInProject($other))->create(['description' => 'Cizi prace']);

        $response = CrmServer::actingAs($this->manager())->tool(ListUninvoicedWorkReportsTool::class, [
            'project_id' => $project->id,
        ]);

        $response->assertOk()
            ->assertSee('Hledana prace')
            ->assertSee('"total_amount": 1500')
            ->assertDontSee('Uz vyfakturovano')
            ->assertDontSee('Cizi prace');
    }

    public function test_it_creates_an_invoice_across_projects(): void
    {
        $manager = $this->manager();
        $first = WorkReport::factory()->forTodo($this->todoInProject(Project::factory()->create()))->create(['hourly_rate' => 800]);
        $second = WorkReport::factory()->forTodo($this->todoInProject(Project::factory()->create()))->create(['hourly_rate' => 900]);

        $response = CrmServer::actingAs($manager)->tool(CreateInvoiceTool::class, [
            'number' => '2026-0042',
            'url' => 'https://example.com/faktura/42',
            'work_report_ids' => [$first->id, $second->id],
            'hourly_rate' => 1200,
        ]);

        $response->assertOk()->assertSee('2026-0042');

        $invoice = Invoice::where('number', '2026-0042')->firstOrFail();
        $this->assertSame($manager->id, $invoice->user_id);
        $this->assertSame($invoice->id, $first->fresh()->invoice_id);
        $this->assertSame($invoice->id, $second->fresh()->invoice_id);
        $this->assertSame('1200.00', $first->fresh()->hourly_rate);
    }

    public function test_it_refuses_reports_already_on_another_invoice(): void
    {
        $report = WorkReport::factory()->invoicedIn(Invoice::factory()->create())->create();

        $response = CrmServer::actingAs($this->manager())->tool(CreateInvoiceTool::class, [
            'number' => 'DUPLICITNI',
            'work_report_ids' => [$report->id],
        ]);

        $response->assertHasErrors();
        $this->assertDatabaseMissing('invoices', ['number' => 'DUPLICITNI']);
    }

    public function test_it_lists_and_shows_invoices(): void
    {
        $invoice = Invoice::factory()->create(['number' => 'FA-777']);
        WorkReport::factory()->invoicedIn($invoice)->create(['minutes' => 60, 'hourly_rate' => 1000, 'description' => 'Vyfakturovana prace']);

        CrmServer::actingAs($this->manager())->tool(ListInvoicesTool::class, [])
            ->assertOk()
            ->assertSee('FA-777')
            ->assertSee('"total_amount": 1000');

        CrmServer::actingAs($this->manager())->tool(GetInvoiceTool::class, ['id' => $invoice->id])
            ->assertOk()
            ->assertSee('Vyfakturovana prace');
    }

    public function test_it_updates_an_invoice_and_moves_reports(): void
    {
        $invoice = Invoice::factory()->create(['number' => 'STARE']);
        $removed = WorkReport::factory()->invoicedIn($invoice)->create();
        $added = WorkReport::factory()->create(['hourly_rate' => 500]);

        $response = CrmServer::actingAs($this->manager())->tool(UpdateInvoiceTool::class, [
            'id' => $invoice->id,
            'number' => 'NOVE',
            'add_work_report_ids' => [$added->id],
            'remove_work_report_ids' => [$removed->id],
        ]);

        $response->assertOk()->assertSee('NOVE');
        $this->assertSame('NOVE', $invoice->fresh()->number);
        $this->assertNull($removed->fresh()->invoice_id);
        $this->assertSame($invoice->id, $added->fresh()->invoice_id);
        $this->assertSame('500.00', $added->fresh()->hourly_rate);
    }

    public function test_it_deletes_an_invoice_and_releases_reports(): void
    {
        $invoice = Invoice::factory()->create();
        $report = WorkReport::factory()->invoicedIn($invoice)->create();

        CrmServer::actingAs($this->manager())->tool(DeleteInvoiceTool::class, ['id' => $invoice->id])->assertOk();

        $this->assertModelMissing($invoice);
        $this->assertNull($report->fresh()->invoice_id);
    }

    public function test_it_updates_a_work_report_and_resets_the_rate(): void
    {
        $karel = User::factory()->create();
        $project = Project::factory()->create(['hourly_rate' => 1000]);
        $project->userRates()->attach($karel->id, ['hourly_rate' => 600]);
        $report = WorkReport::factory()->forTodo($this->todoInProject($project))->create(['user_id' => $karel->id, 'hourly_rate' => 999]);

        CrmServer::actingAs($this->manager())->tool(UpdateWorkReportTool::class, [
            'id' => $report->id,
            'minutes' => 45,
            'hourly_rate' => null,
        ])->assertOk();

        $this->assertSame(45, $report->fresh()->minutes);
        $this->assertSame('600.00', $report->fresh()->hourly_rate);
    }

    public function test_invoiced_work_reports_cannot_be_changed_or_deleted(): void
    {
        $report = WorkReport::factory()->invoicedIn(Invoice::factory()->create())->create(['minutes' => 30]);

        CrmServer::actingAs($this->manager())->tool(UpdateWorkReportTool::class, ['id' => $report->id, 'minutes' => 60])
            ->assertHasErrors();
        CrmServer::actingAs($this->manager())->tool(DeleteWorkReportTool::class, ['id' => $report->id])
            ->assertHasErrors();

        $this->assertSame(30, $report->fresh()->minutes);
    }

    public function test_it_deletes_a_work_report(): void
    {
        $report = WorkReport::factory()->create();

        CrmServer::actingAs($this->manager())->tool(DeleteWorkReportTool::class, ['id' => $report->id])->assertOk();

        $this->assertModelMissing($report);
    }

    public function test_it_sets_per_person_project_rates(): void
    {
        $karel = User::factory()->create(['name' => 'Karel']);
        $project = Project::factory()->create();

        CrmServer::actingAs($this->manager())->tool(UpdateProjectTool::class, [
            'id' => $project->id,
            'user_rates' => [['user_id' => $karel->id, 'hourly_rate' => 700]],
        ])->assertOk();

        $this->assertSame('700.00', $project->rateFor($karel->id));

        CrmServer::actingAs($this->manager())->tool(GetProjectTool::class, ['id' => $project->id])
            ->assertOk()
            ->assertSee('"user_name": "Karel"');
    }

    public function test_a_user_without_a_role_cannot_invoice(): void
    {
        $report = WorkReport::factory()->create();

        CrmServer::actingAs(User::factory()->create(['role' => null]))->tool(CreateInvoiceTool::class, [
            'number' => 'X',
            'work_report_ids' => [$report->id],
        ])->assertHasErrors();

        $this->assertNull($report->fresh()->invoice_id);
    }
}
