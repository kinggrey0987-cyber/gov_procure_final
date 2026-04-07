<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\Office;
use App\Models\PublicDisclosure;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected User $officeUserA;
    protected User $officeUserB;
    protected User $adminUser;
    protected User $publicUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Notification Office',
            'code' => 'NOTIF_OFFICE',
            'description' => 'Notification test office',
            'is_active' => true,
        ]);

        $this->officeUserA = $this->createUser(UserRole::OFFICE_USER);
        $this->officeUserB = $this->createUser(UserRole::OFFICE_USER);
        $this->adminUser = $this->createUser(UserRole::ADMIN);
        $this->publicUser = $this->createUser(UserRole::PUBLIC);
    }

    public function test_user_can_only_see_own_notifications(): void
    {
        $service = app(NotificationService::class);
        $service->notifyUser($this->officeUserA, 'A1', 'Message A1', 'OFFICE_UPDATE');
        $service->notifyUser($this->officeUserB, 'B1', 'Message B1', 'OFFICE_UPDATE');

        $this->actingAs($this->officeUserA)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'A1')
            );
    }

    public function test_notifications_index_keeps_bell_summary_separate_from_page_payload(): void
    {
        $service = app(NotificationService::class);
        $service->notifyUser($this->officeUserA, 'Inbox', 'Inbox message', 'OFFICE_UPDATE');

        $this->actingAs($this->officeUserA)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->has('notifications.data', 1)
                ->has('notificationSummary.unread_count')
                ->has('notificationSummary.recent')
            );
    }

    public function test_viewing_another_users_notification_is_denied(): void
    {
        $notification = Notification::query()->create([
            'user_id' => $this->officeUserB->id,
            'title' => 'Private',
            'message' => 'Should not be visible',
            'notification_type' => 'PRIVATE',
            'reference_type' => null,
            'reference_id' => null,
            'is_read' => false,
        ]);

        $this->actingAs($this->officeUserA)
            ->get(route('notifications.show', $notification->notification_id))
            ->assertForbidden();
    }

    public function test_mark_as_read_works_only_for_owner(): void
    {
        $notification = Notification::query()->create([
            'user_id' => $this->officeUserA->id,
            'title' => 'Needs Reading',
            'message' => 'Unread message',
            'notification_type' => 'OFFICE_UPDATE',
            'reference_type' => null,
            'reference_id' => null,
            'is_read' => false,
        ]);

        $this->actingAs($this->officeUserB)
            ->put(route('notifications.mark-read', $notification->notification_id))
            ->assertForbidden();

        $this->actingAs($this->officeUserA)
            ->put(route('notifications.mark-read', $notification->notification_id))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notification_id' => $notification->notification_id,
            'is_read' => true,
        ]);
    }

    public function test_mark_all_as_read_affects_only_current_user_inbox(): void
    {
        Notification::query()->create([
            'user_id' => $this->officeUserA->id,
            'title' => 'A1',
            'message' => 'A msg 1',
            'notification_type' => 'TYPE_A',
            'reference_type' => null,
            'reference_id' => null,
            'is_read' => false,
        ]);
        Notification::query()->create([
            'user_id' => $this->officeUserA->id,
            'title' => 'A2',
            'message' => 'A msg 2',
            'notification_type' => 'TYPE_A',
            'reference_type' => null,
            'reference_id' => null,
            'is_read' => false,
        ]);
        Notification::query()->create([
            'user_id' => $this->officeUserB->id,
            'title' => 'B1',
            'message' => 'B msg 1',
            'notification_type' => 'TYPE_B',
            'reference_type' => null,
            'reference_id' => null,
            'is_read' => false,
        ]);

        $this->actingAs($this->officeUserA)
            ->put(route('notifications.mark-all-read'))
            ->assertRedirect();

        $this->assertSame(0, Notification::query()->where('user_id', $this->officeUserA->id)->where('is_read', false)->count());
        $this->assertSame(1, Notification::query()->where('user_id', $this->officeUserB->id)->where('is_read', false)->count());
    }

    public function test_reference_linkage_is_stored_and_rendered_for_owner(): void
    {
        $disclosure = PublicDisclosure::query()->create([
            'title' => 'Published Notice',
            'type' => 'PROCUREMENT',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Notification linkage target',
            'published_by' => $this->adminUser->id,
            'published_at' => now(),
            'file_path' => null,
            'status' => 'PUBLISHED',
        ]);

        Notification::query()->create([
            'user_id' => $this->officeUserA->id,
            'title' => 'Disclosure Published',
            'message' => 'A disclosure is now public.',
            'notification_type' => 'DISCLOSURE',
            'reference_type' => PublicDisclosure::class,
            'reference_id' => $disclosure->disclosure_id,
            'is_read' => false,
        ]);

        $this->actingAs($this->officeUserA)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.data.0.reference_type', PublicDisclosure::class)
                ->where('notifications.data.0.reference_id', $disclosure->disclosure_id)
            );
    }

    public function test_public_cannot_access_internal_notification_pages(): void
    {
        $this->actingAs($this->publicUser)
            ->get(route('notifications.index'))
            ->assertForbidden();
    }

    public function test_admin_has_no_cross_user_inbox_bypass(): void
    {
        $notification = Notification::query()->create([
            'user_id' => $this->officeUserA->id,
            'title' => 'Office Notice',
            'message' => 'For office user only',
            'notification_type' => 'OFFICE',
            'reference_type' => null,
            'reference_id' => null,
            'is_read' => false,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('notifications.show', $notification->notification_id))
            ->assertForbidden();
    }

    private function createUser(UserRole $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Notification test role',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($this->office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $this->office->id,
        ]);
    }
}
