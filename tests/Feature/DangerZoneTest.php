<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The Settings "Danger Zone": Reset Database and Restore Backup
 * (BackupController::resetDatabase()/restoreBackup()). Both sit behind
 * settings.restore, which - unlike settings.manage - only Super
 * Administrator holds (see RoleAndPermissionSeeder), and both require
 * ResetDatabaseRequest/RestoreBackupRequest's typed confirmation word
 * plus the acting user's current password.
 *
 * Uses DatabaseMigrations, not the usual RefreshDatabase, deliberately:
 * resetDatabase() TRUNCATEs tables (implicit-commit DDL in MySQL/InnoDB)
 * and restoreBackup() runs DROP/CREATE TABLE over a *second*, independent
 * mysqli connection (see BackupController). Either one would fight
 * RefreshDatabase's ambient open transaction - TRUNCATE would silently
 * force-commit it (breaking rollback-based isolation for this file), and
 * the second connection's DROP TABLE could block for a very long time
 * waiting on a metadata lock the transaction is holding. DatabaseMigrations
 * runs real migrate/rollback per test instead of wrapping it in a
 * transaction, so neither problem applies here.
 */
class DangerZoneTest extends TestCase
{
    use DatabaseMigrations;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Administrator');
    }

    public function test_a_school_administrator_cannot_reach_reset_or_restore(): void
    {
        $schoolAdmin = User::factory()->create();
        $schoolAdmin->assignRole('School Administrator');

        $this->actingAs($schoolAdmin)->post(route('settings.database.reset'), [
            'confirmation_phrase' => 'RESET',
            'current_password' => 'password',
        ])->assertStatus(403);

        $this->actingAs($schoolAdmin)->post(route('settings.backup.restore'), [
            'confirmation_phrase' => 'RESTORE',
            'current_password' => 'password',
        ])->assertStatus(403);
    }

    public function test_an_accountant_cannot_reach_reset_or_restore(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');

        $this->actingAs($accountant)->post(route('settings.database.reset'), [
            'confirmation_phrase' => 'RESET',
            'current_password' => 'password',
        ])->assertStatus(403);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post(route('settings.database.reset'))->assertRedirect('/login');
        $this->post(route('settings.backup.restore'))->assertRedirect('/login');
    }

    public function test_reset_rejects_the_wrong_confirmation_phrase(): void
    {
        Student::factory()->create();

        $response = $this->actingAs($this->superAdmin)->post(route('settings.database.reset'), [
            'confirmation_phrase' => 'reset', // wrong case, must be exactly "RESET"
            'current_password' => 'password',
        ]);

        $response->assertSessionHasErrors('confirmation_phrase');
        $this->assertDatabaseCount('students', 1);
    }

    public function test_reset_rejects_the_wrong_password(): void
    {
        Student::factory()->create();

        $response = $this->actingAs($this->superAdmin)->post(route('settings.database.reset'), [
            'confirmation_phrase' => 'RESET',
            'current_password' => 'not-the-real-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('students', 1);
    }

    public function test_reset_wipes_school_data_but_keeps_accounts_roles_and_settings(): void
    {
        Student::factory()->count(3)->create();
        $expenseCategory = ExpenseCategory::factory()->create();
        $settingId = Setting::current()->id;

        $response = $this->actingAs($this->superAdmin)->post(route('settings.database.reset'), [
            'confirmation_phrase' => 'RESET',
            'current_password' => 'password',
        ]);

        $response->assertRedirect(route('settings.edit'));

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseMissing('expense_categories', ['id' => $expenseCategory->id]);

        // Protected: accounts, roles/permissions and settings survive.
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
        $this->assertTrue($this->superAdmin->fresh()->hasRole('Super Administrator'));
        $this->assertDatabaseHas('settings', ['id' => $settingId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reset', 'module' => 'database']);
    }

    public function test_restore_rejects_the_wrong_confirmation_phrase(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('settings.backup.restore'), [
            'backup_file' => UploadedFile::fake()->create('backup.zip', 10),
            'confirmation_phrase' => 'restore',
            'current_password' => 'password',
        ]);

        $response->assertSessionHasErrors('confirmation_phrase');
    }

    public function test_restore_rejects_the_wrong_password(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('settings.backup.restore'), [
            'backup_file' => UploadedFile::fake()->create('backup.zip', 10),
            'confirmation_phrase' => 'RESTORE',
            'current_password' => 'not-the-real-password',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_super_admin_can_restore_a_previously_downloaded_backup(): void
    {
        $category = ExpenseCategory::factory()->create(['name' => 'Utilities restore test']);

        $downloadResponse = $this->actingAs($this->superAdmin)->get(route('settings.backup'));
        $downloadResponse->assertStatus(200);

        $backupPath = $downloadResponse->getFile()->getPathname();
        $this->assertFileExists($backupPath);

        // Wipe the category so restoring the backup is a real, observable
        // change rather than a no-op.
        $category->delete();
        $this->assertDatabaseMissing('expense_categories', ['name' => 'Utilities restore test']);

        $uploadedBackup = new UploadedFile($backupPath, 'backup.zip', 'application/zip', null, true);

        $restoreResponse = $this->actingAs($this->superAdmin)->post(route('settings.backup.restore'), [
            'backup_file' => $uploadedBackup,
            'confirmation_phrase' => 'RESTORE',
            'current_password' => 'password',
        ]);

        $restoreResponse->assertRedirect(route('settings.edit'));
        $this->assertDatabaseHas('expense_categories', ['name' => 'Utilities restore test']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'restore', 'module' => 'backup']);
    }
}
