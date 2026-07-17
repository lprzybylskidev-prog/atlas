<?php

declare(strict_types=1);

namespace Tests\Feature\Files;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Files\Application\Enums\FileScanState;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class FilesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_file_scan_metadata_and_queue_rescan(): void
    {
        Storage::fake('atlas_files');
        Queue::fake();
        Config::set('atlas.files.disk', 'atlas_files');
        Config::set('atlas.files.scanner', 'fake');
        Config::set('atlas.files.fake_scanner_result', 'infected');

        Config::set('atlas.files.allowed_extensions', ['txt']);
        Config::set('atlas.files.allowed_mime_types', ['text/plain', 'application/octet-stream']);

        $stored = $this->app->make(FileStorage::class)->storeUpload(UploadedFile::fake()->createWithContent('evidence.txt', 'Evidence content.'));
        [$admin, $team] = $this->adminWithTeam();

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/files')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Files/Index')
                ->where('files.0.publicId', $stored->publicId)
                ->where('files.0.originalName', 'evidence.txt')
                ->where('files.0.scanState', FileScanState::Pending->value));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/files/'.$stored->publicId.'/rescan')
            ->assertRedirect(route('admin.files.index'));

        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'public_id' => $stored->publicId,
            'scan_state' => FileScanState::Pending->value,
        ]);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $admin = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => '01J00000000000000000000001',
            'name' => 'Operations',
            'slug' => 'operations',
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        $this->app['db']->table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app['db']->table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $admin->id,
            'team_id' => $team->id,
        ]);

        return [$admin, $team];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];
    }
}
