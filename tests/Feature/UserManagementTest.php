<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');
    }

    public function test_administrator_can_create_a_user_with_a_role(): void
    {
        $response = $this->actingAs($this->admin)->post('/users', [
            'name' => 'New Teacher',
            'email' => 'new.teacher@school.local',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
            'roles' => ['Teacher'],
            'is_active' => 1,
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 'new.teacher@school.local']);
        $this->assertTrue(User::where('email', 'new.teacher@school.local')->first()->hasRole('Teacher'));
    }

    public function test_a_user_cannot_be_created_without_a_role(): void
    {
        $response = $this->actingAs($this->admin)->post('/users', [
            'name' => 'No Role',
            'email' => 'norole@school.local',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
            'roles' => [],
        ]);

        $response->assertSessionHasErrors('roles');
        $this->assertDatabaseMissing('users', ['email' => 'norole@school.local']);
    }

    public function test_disabling_a_user_blocks_their_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Teacher');

        $this->actingAs($this->admin)->patch("/users/{$user->id}/toggle-active");

        $this->assertFalse($user->fresh()->is_active);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_an_administrator_cannot_disable_their_own_account(): void
    {
        $response = $this->actingAs($this->admin)->patch("/users/{$this->admin->id}/toggle-active");

        $response->assertStatus(403);
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_resetting_a_password_forces_the_user_to_change_it_on_next_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Teacher');

        $this->actingAs($this->admin)->post("/users/{$user->id}/reset-password");

        $this->assertTrue($user->fresh()->must_change_password);
    }
}
