<?php

namespace Tests\Feature;

use App\Actions\SaveCalculation;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SaveCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_duplicate_unique_ids(): void
    {
        $svc = Service::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(SaveCalculation::class)->create([
            'customer_name' => 'Test', 'customer_email' => 't@t.cz', 'customer_phone' => '1',
            'services' => [
                ['id' => $svc->id, 'unique_id' => 'x', 'price' => 1, 'days' => 1, 'payment_period' => 'once'],
                ['id' => $svc->id, 'unique_id' => 'x', 'price' => 1, 'days' => 1, 'payment_period' => 'once'],
            ],
        ], null);
    }
}
