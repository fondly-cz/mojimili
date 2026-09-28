<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    public function test_managers_cannot_access_user_management(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $other = User::factory()->create();

        $this->actingAs($manager)->get('/users')->assertForbidden();
        $this->actingAs($manager)->post('/users', ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->actingAs($manager)->put("/users/{$other->id}", ['name' => 'X', 'email' => $other->email])->assertForbidden();
        $this->actingAs($manager)->delete("/users/{$other->id}")->assertForbidden();
    }

    public function test_it_lists_and_filters_users_by_role(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Karel', 'role' => UserRole::MANAGER]);
        User::factory()->create(['name' => 'Honza', 'role' => null]);

        $this->actingAs($admin)
            ->get('/users?role=none')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Honza')
                ->has('roles', count(UserRole::cases()))
            );
    }

    public function test_admin_creates_user_with_role(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'Karel Nový',
                'email' => 'karel@example.com',
                'role' => 'manager',
                'password' => 'tajneheslo',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::where('email', 'karel@example.com')->firstOrFail();
        $this->assertSame(UserRole::MANAGER, $user->role);
        $this->assertTrue(Hash::check('tajneheslo', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_admin_creates_user_without_role_and_password(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', ['name' => 'Externista', 'email' => 'ext@example.com', 'role' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'ext@example.com')->firstOrFail()->role);
    }

    public function test_email_must_be_unique(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/users', ['name' => 'Duplicita', 'email' => $admin->email])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_updates_user_role_and_keeps_password_when_empty(): void
    {
        $user = User::factory()->create(['role' => UserRole::MANAGER]);
        $originalPassword = $user->password;

        $this->actingAs($this->admin())
            ->put("/users/{$user->id}", [
                'name' => 'Přejmenovaný',
                'email' => $user->email,
                'role' => 'admin',
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Přejmenovaný', $user->name);
        $this->assertSame(UserRole::ADMIN, $user->role);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'manager'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::ADMIN, $admin->refresh()->role);
    }

    public function test_admin_deletes_user_and_keeps_their_todos(): void
    {
        $user = User::factory()->create();
        $todo = Todo::factory()->create(['assigned_user_id' => $user->id]);

        $this->actingAs($this->admin())
            ->delete("/users/{$user->id}")
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($user);
        $this->assertNull($todo->refresh()->assigned_user_id);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/users/{$admin->id}")
            ->assertSessionHasErrors('user');

        $this->assertModelExists($admin);
    }
}
