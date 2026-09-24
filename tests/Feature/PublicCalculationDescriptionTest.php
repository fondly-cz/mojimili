<?php

namespace Tests\Feature;

use App\Models\Calculation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCalculationDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_passes_formatted_description_unchanged(): void
    {
        $html = '<h4>Cíl</h4><blockquote><p>Citace</p></blockquote><ul><li><p>Bod</p><ul><li><p>Vnořený</p></li></ul></li></ul>'
            .'<hr><table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table><p><a href="https://example.com">odkaz</a></p>';

        $calculation = Calculation::factory()->create(['description' => $html]);

        $this->get("/c/{$calculation->access_token}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calculations/Show')
                ->where('is_public', true)
                ->where('calculation.description', $html)
            );
    }
}
