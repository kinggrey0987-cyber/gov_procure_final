<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\RecordArchive;
use App\Models\Role;
use App\Models\SystemBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveAndBackupAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected Office $technicalOffice;
    protected User $admin;
    protected User $executive;
    protected User $officeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Archive Test Office',
            'code' => 'ARCHIVE_OFF',
            'description' => 'Archive test office',
            'is_active' => true,
        ]);

        $this->technicalOffice = Office::query()->create([
            'name' => 'Technical Administration',
            'code' => 'TECH_ADMIN',
            'description' => 'Technical office',
            'is_active' => true,
        ]);

        foreach (UserRole::cases() as $role) {
            $this->ensureRole($role);
        }

        $this->admin = $this->createUser(UserRole::ADMIN, $this->technicalOffice, 'admin.backup@gsc.local');
        $this->executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->office, 'exec.archive@gsc.local');
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office, 'office.user@gsc.local');
    }

    public function test_exec_can_generate_record_archive_and_download_when_file_exists(): void
    {
        Storage::fake('local');

        $this->actingAs($this->executive)
            ->post(route('executive.record-archives.store'), [
                'archive_year' => now()->year,
                'archive_scope' => 'governance_yearly',
                'notes' => 'Yearly governance archive package.',
            ])
            ->assertRedirect(route('executive.record-archives.index'));

        $archive = RecordArchive::query()->latest('archive_id')->firstOrFail();

        $this->assertDatabaseHas('record_archives', [
            'archive_id' => $archive->archive_id,
            'archive_year' => now()->year,
            'archive_scope' => 'governance_yearly',
            'generated_by' => $this->executive->id,
        ]);

        $this->assertTrue(Storage::disk('local')->exists((string) $archive->file_path));

        $this->actingAs($this->executive)
            ->get(route('executive.record-archives.download', $archive->archive_id))
            ->assertOk();
    }

    public function test_admin_cannot_access_or_generate_record_archives(): void
    {
        $this->actingAs($this->admin)
            ->get(route('executive.record-archives.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('executive.record-archives.store'), [
                'archive_year' => now()->year,
                'archive_scope' => 'governance_yearly',
            ])
            ->assertForbidden();
    }

    public function test_exec_can_delete_record_archive_and_underlying_file(): void
    {
        Storage::fake('local');

        $this->actingAs($this->executive)
            ->post(route('executive.record-archives.store'), [
                'archive_year' => now()->year,
                'archive_scope' => 'governance_yearly',
                'notes' => 'Temporary archive for delete test.',
            ])
            ->assertRedirect(route('executive.record-archives.index'));

        $archive = RecordArchive::query()->latest('archive_id')->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists((string) $archive->file_path));

        $this->actingAs($this->executive)
            ->delete(route('executive.record-archives.destroy', $archive->archive_id))
            ->assertRedirect(route('executive.record-archives.index'));

        $this->assertDatabaseMissing('record_archives', [
            'archive_id' => $archive->archive_id,
        ]);
        $this->assertFalse(Storage::disk('local')->exists((string) $archive->file_path));
    }

    public function test_admin_can_create_system_backup_and_download_generated_file(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin-tech.system-backups.store'), [
                'backup_type' => 'database_backup',
                'notes' => 'Scheduled admin technical backup.',
            ])
            ->assertRedirect(route('admin-tech.system-backups.index'));

        $backup = SystemBackup::query()->latest('backup_id')->firstOrFail();

        $this->assertDatabaseHas('system_backups', [
            'backup_id' => $backup->backup_id,
            'backup_type' => 'database_backup',
            'created_by' => $this->admin->id,
            'backup_status' => 'completed',
        ]);

        $this->assertNotNull($backup->payload);

        $this->assertTrue(Storage::disk('local')->exists((string) $backup->file_path));

        $this->actingAs($this->admin)
            ->get(route('admin-tech.system-backups.download', $backup->backup_id))
            ->assertOk();
    }

    public function test_admin_can_delete_system_backup_and_underlying_file(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin-tech.system-backups.store'), [
                'backup_type' => 'database_backup',
                'notes' => 'Temporary backup for delete test.',
            ])
            ->assertRedirect(route('admin-tech.system-backups.index'));

        $backup = SystemBackup::query()->latest('backup_id')->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists((string) $backup->file_path));

        $this->actingAs($this->admin)
            ->delete(route('admin-tech.system-backups.destroy', $backup->backup_id))
            ->assertRedirect(route('admin-tech.system-backups.index'));

        $this->assertDatabaseMissing('system_backups', [
            'backup_id' => $backup->backup_id,
        ]);
        $this->assertFalse(Storage::disk('local')->exists((string) $backup->file_path));
    }

    public function test_admin_can_download_backup_from_payload_when_file_is_unavailable(): void
    {
        Storage::fake('local');

        $backup = SystemBackup::query()->create([
            'backup_type' => 'database_backup',
            'file_name' => 'database_backup_payload.json',
            'file_path' => 'system-backups/missing/database_backup_payload.json',
            'file_size' => null,
            'payload' => [
                'format' => 'json',
                'content' => [
                    'backup_type' => 'database_backup',
                    'created_at' => now()->toIso8601String(),
                    'notes' => 'Payload fallback test',
                ],
            ],
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'backup_status' => 'completed',
            'notes' => null,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin-tech.system-backups.download', $backup->backup_id))
            ->assertOk();
    }

    public function test_exec_cannot_access_or_manage_system_backups(): void
    {
        $this->actingAs($this->executive)
            ->get(route('admin-tech.system-backups.index'))
            ->assertForbidden();

        $this->actingAs($this->executive)
            ->post(route('admin-tech.system-backups.store'), [
                'backup_type' => 'database_backup',
            ])
            ->assertForbidden();
    }

    public function test_download_routes_are_safe_for_missing_files(): void
    {
        Storage::fake('local');

        $archive = RecordArchive::query()->create([
            'archive_year' => now()->year,
            'archive_scope' => 'governance_yearly',
            'file_name' => 'missing_archive.zip',
            'file_path' => 'record-archives/missing/missing_archive.zip',
            'file_size' => null,
            'generated_by' => $this->executive->id,
            'generated_at' => now(),
            'notes' => null,
        ]);

        $backup = SystemBackup::query()->create([
            'backup_type' => 'database_backup',
            'file_name' => 'missing_backup.json',
            'file_path' => 'system-backups/missing/missing_backup.json',
            'file_size' => null,
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'backup_status' => 'completed',
            'notes' => null,
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.record-archives.download', $archive->archive_id))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin-tech.system-backups.download', $backup->backup_id))
            ->assertNotFound();
    }

    public function test_non_exec_non_admin_roles_cannot_access_archive_or_backup_modules(): void
    {
        $this->actingAs($this->officeUser)
            ->get(route('executive.record-archives.index'))
            ->assertForbidden();

        $this->actingAs($this->officeUser)
            ->get(route('admin-tech.system-backups.index'))
            ->assertForbidden();
    }

    private function createUser(UserRole $role, Office $office, string $email): User
    {
        $roleModel = $this->ensureRole($role);

        return User::factory()
            ->withRole($role)
            ->forOffice($office)
            ->create([
                'email' => $email,
                'role_id' => $roleModel->id,
                'office_id' => $office->id,
            ]);
    }

    private function ensureRole(UserRole $role): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for archive/backup tests',
                'is_active' => true,
            ]
        );
    }
}

