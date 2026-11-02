<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_super_administrator_can_access_user_management(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Administrator');

        $this->actingAs($admin)->get('/users')->assertStatus(200);
    }

    public function test_teacher_cannot_access_user_management(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/users')->assertStatus(403);
    }

    public function test_teacher_cannot_access_school_settings(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/settings')->assertStatus(403);
    }

    public function test_school_administrator_can_manage_settings_but_is_not_a_super_administrator(): void
    {
        $schoolAdmin = User::factory()->create();
        $schoolAdmin->assignRole('School Administrator');

        $this->actingAs($schoolAdmin)->get('/settings')->assertStatus(200);
        $this->assertFalse($schoolAdmin->hasRole('Super Administrator'));
    }

    public function test_every_authenticated_role_can_view_the_dashboard(): void
    {
        foreach (RoleAndPermissionSeeder::ROLES as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->actingAs($user)->get('/dashboard')->assertStatus(200);
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
