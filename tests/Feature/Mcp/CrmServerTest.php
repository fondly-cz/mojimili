<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateCalculationTool;
use App\Mcp\Tools\CreateServiceTool;
use App\Mcp\Tools\ListServicesTool;
use App\Models\Calculation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_service(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = CrmServer::actingAs($admin)->tool(CreateServiceTool::class, [
            'name' => 'Webhosting',
            'category' => 'Hosting',
            'cost' => 100,
            'margin' => 30,
            'days' => 0,
            'payment_period' => 'monthly',
        ]);

        $response->assertOk()->assertSee('Webhosting');

        $this->assertDatabaseHas('services', [
            'name' => 'Webhosting',
            'category' => 'Hosting',
            'payment_period' => 'monthly',
        ]);
    }

    public function test_manager_cannot_create_a_service(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = CrmServer::actingAs($manager)->tool(CreateServiceTool::class, [
            'name' => 'Webhosting',
            'category' => 'Hosting',
            'cost' => 100,
            'margin' => 30,
            'days' => 0,
            'payment_period' => 'monthly',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('services', 0);
    }

    public function test_user_without_role_cannot_list_services(): void
    {
        $user = User::factory()->create(['role' => null]);

        CrmServer::actingAs($user)
            ->tool(ListServicesTool::class, [])
            ->assertHasErrors();
    }

    public function test_inactive_services_are_hidden_by_default(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);

        Service::create([
            'name' => 'Aktivní služba', 'category' => 'Vývoj', 'cost' => 1000,
            'margin' => 30, 'days' => 2, 'payment_period' => 'once', 'is_active' => true,
        ]);
        Service::create([
            'name' => 'Zrušená služba', 'category' => 'Vývoj', 'cost' => 1000,
            'margin' => 30, 'days' => 2, 'payment_period' => 'once', 'is_active' => false,
        ]);

        CrmServer::actingAs($user)
            ->tool(ListServicesTool::class, [])
            ->assertOk()
            ->assertSee('Aktivní služba')
            ->assertDontSee('Zrušená služba');
    }

    public function test_it_creates_a_calculation_with_nested_items_and_catalog_defaults(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);

        $parentService = Service::create([
            'name' => 'Web na míru', 'category' => 'Vývoj', 'cost' => 10000,
            'margin' => 30, 'days' => 10, 'payment_period' => 'once',
            'description' => 'Popis z katalogu',
        ]);
        $childService = Service::create([
            'name' => 'Webhosting', 'category' => 'Hosting', 'cost' => 100,
            'margin' => 0, 'days' => 0, 'payment_period' => 'monthly',
        ]);

        $response = CrmServer::actingAs($user)->tool(CreateCalculationTool::class, [
            'customer_name' => 'Jan Novák',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '+420 777 123 456',
            'items' => [
                ['service_id' => $parentService->id, 'key' => 'web'],
                ['service_id' => $childService->id, 'parent_key' => 'web', 'price' => 150],
            ],
        ]);

        $response->assertOk()->assertSee('Jan Novák');

        $calculation = Calculation::with('items')->firstOrFail();

        $this->assertSame($user->id, $calculation->user_id);
        $this->assertCount(2, $calculation->items);

        $parentItem = $calculation->items->firstWhere('service_id', $parentService->id);
        $childItem = $calculation->items->firstWhere('service_id', $childService->id);

        // Price, days, period and description fall back to the service catalog.
        $this->assertSame('13000.00', $parentItem->price);
        $this->assertSame(10, $parentItem->days);
        $this->assertSame('Popis z katalogu', $parentItem->description);
        $this->assertNull($parentItem->parent_id);

        // Explicit price wins over the catalog price.
        $this->assertSame('150.00', $childItem->price);
        $this->assertSame('monthly', $childItem->payment_period->value);
        $this->assertSame($parentItem->id, $childItem->parent_id);
    }

    public function test_it_rejects_an_item_referencing_an_unknown_parent_key(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);

        $service = Service::create([
            'name' => 'Web na míru', 'category' => 'Vývoj', 'cost' => 10000,
            'margin' => 30, 'days' => 10, 'payment_period' => 'once',
        ]);

        CrmServer::actingAs($user)
            ->tool(CreateCalculationTool::class, [
                'customer_name' => 'Jan Novák',
                'customer_email' => 'jan@example.com',
                'customer_phone' => '+420 777 123 456',
                'items' => [
                    ['service_id' => $service->id, 'parent_key' => 'neexistuje'],
                ],
            ])
            ->assertHasErrors();

        $this->assertDatabaseCount('calculations', 0);
    }
}
