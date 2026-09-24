<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\UpdateCalculationTool;
use App\Models\Calculation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalculationDescriptionHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_text_description_is_converted_to_paragraphs(): void
    {
        $calculation = Calculation::factory()->create([
            'description' => "První odstavec.\n\nDruhý odstavec\ns novým řádkem.",
        ]);

        $this->assertSame(
            "<p>První odstavec.</p>\n<p>Druhý odstavec<br>\ns novým řádkem.</p>",
            $calculation->description
        );
    }

    public function test_html_description_from_editor_is_kept_unchanged(): void
    {
        $html = '<p>Odstavec</p><ul><li><p>Bod</p></li></ul>';

        $calculation = Calculation::factory()->create(['description' => $html]);

        $this->assertSame($html, $calculation->description);
    }

    public function test_empty_description_stays_empty(): void
    {
        $this->assertNull(Calculation::factory()->create(['description' => null])->description);
        $this->assertSame('', Calculation::factory()->create(['description' => ''])->description);
    }

    public function test_mcp_update_stores_markdown_description_as_html(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);
        $calculation = Calculation::factory()->create(['user_id' => $user->id]);

        CrmServer::actingAs($user)
            ->tool(UpdateCalculationTool::class, [
                'id' => $calculation->id,
                'description' => "Úvod.\n\nCeny jsou **bez DPH**.",
            ])
            ->assertOk();

        $this->assertSame(
            "<p>Úvod.</p>\n<p>Ceny jsou <strong>bez DPH</strong>.</p>",
            $calculation->refresh()->description
        );
    }

    public function test_migration_converts_existing_plain_text_descriptions(): void
    {
        $plain = Calculation::factory()->create();
        $html = Calculation::factory()->create(['description' => '<p>Už HTML</p>']);
        DB::table('calculations')->where('id', $plain->id)->update(['description' => "A\n\nB"]);

        $migration = require database_path('migrations/2026_09_24_100000_convert_plain_text_calculation_descriptions_to_html.php');
        $migration->up();

        $this->assertSame("<p>A</p>\n<p>B</p>", $plain->refresh()->description);
        $this->assertSame('<p>Už HTML</p>', $html->refresh()->description);
    }
}
