<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one-click manual backup download (BackupController). The full
 * dump+zip runs for real against the test database here (phpunit.xml
 * points DB_CONNECTION/DB_DATABASE at a real MySQL testing database, not
 * sqlite, so the hand-rolled SHOW TABLES/SHOW CREATE TABLE dump works
 * identically to production) - this test only checks permission gating
 * and the resulting download response, not the archive's byte contents.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_a_super_administrator_can_download_a_backup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Administrator');

        $response = $this->actingAs($admin)->get(route('settings.backup'));

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.zip', $response->headers->get('content-disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'download',
            'module' => 'backup',
            'user_id' => $admin->id,
        ]);
    }

    public function test_a_user_without_settings_permission_cannot_download_a_backup(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');

        $this->actingAs($accountant)->get(route('settings.backup'))->assertStatus(403);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'download', 'module' => 'backup']);
    }

    public function test_guests_cannot_download_a_backup(): void
    {
        $this->get(route('settings.backup'))->assertRedirect('/login');
    }
}
