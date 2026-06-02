<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Delivery;
use App\Models\Notification;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PublicDisclosure;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
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
    protected User $gsoOfficer;
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
        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER);
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

    public function test_gso_alert_notification_has_accessible_dashboard_target(): void
    {
        $alert = Alert::query()->create([
            'alert_type' => 'LOW_STOCK',
            'severity' => 'MEDIUM',
            'status' => 'OPEN',
            'alert_message' => 'Printer ink is at low stock and needs replenishment soon.',
            'detected_at' => now(),
        ]);

        $notification = Notification::query()->create([
            'user_id' => $this->gsoOfficer->id,
            'title' => 'Operational Alert',
            'message' => 'Printer ink is at low stock and needs replenishment soon.',
            'notification_type' => 'OPERATIONAL_ALERT',
            'reference_type' => Alert::class,
            'reference_id' => $alert->alerts_id,
            'is_read' => false,
        ]);

        $this->actingAs($this->gsoOfficer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.data.0.reference_label', 'Alert #'.$alert->alerts_id)
                ->where('notifications.data.0.target_label', 'View Alert Queue')
                ->where('notifications.data.0.target_url', route('inventory.alerts.index', [
                    'search' => 'LOW_STOCK',
                    'status' => 'OPEN',
                ]))
            );

        $this->actingAs($this->gsoOfficer)
            ->get(route('notifications.show', $notification->notification_id))
            ->assertRedirect(route('inventory.alerts.index', [
                'search' => 'LOW_STOCK',
                'status' => 'OPEN',
            ]));

        $this->assertDatabaseHas('notifications', [
            'notification_id' => $notification->notification_id,
            'is_read' => true,
        ]);
    }

    public function test_office_workflow_notifications_for_procurement_records_open_the_owned_supply_request(): void
    {
        $workflow = $this->createProcurementWorkflowContext($this->office, $this->officeUserA);

        foreach ([
            PurchaseRequest::class => $workflow['purchase_request']->purchase_request_id,
            PurchaseOrder::class => $workflow['purchase_order']->purchase_order_id,
            Delivery::class => $workflow['delivery']->delivery_id,
        ] as $referenceType => $referenceId) {
            Notification::query()->create([
                'user_id' => $this->officeUserA->id,
                'title' => 'Workflow Updated',
                'message' => 'A linked workflow record was updated.',
                'notification_type' => 'WORKFLOW_UPDATE',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'is_read' => false,
            ]);
        }

        $this->actingAs($this->officeUserA)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.data.0.target_url', route('office.supply-requests.show', $workflow['supply_request']->supply_request_id))
                ->where('notifications.data.0.target_label', 'View Supply Request')
                ->where('notifications.data.1.target_url', route('office.supply-requests.show', $workflow['supply_request']->supply_request_id))
                ->where('notifications.data.1.target_label', 'View Supply Request')
                ->where('notifications.data.2.target_url', route('office.supply-requests.show', $workflow['supply_request']->supply_request_id))
                ->where('notifications.data.2.target_label', 'View Supply Request')
            );
    }

    public function test_office_workflow_notification_does_not_open_another_offices_supply_request(): void
    {
        $workflow = $this->createProcurementWorkflowContext($this->office, $this->officeUserA);
        $otherOffice = Office::query()->create([
            'name' => 'Other Notification Office',
            'code' => 'OTHER_NOTIF',
            'description' => 'Other office',
            'is_active' => true,
        ]);
        $otherOfficeUser = $this->createUser(UserRole::OFFICE_USER, $otherOffice);

        $notification = Notification::query()->create([
            'user_id' => $otherOfficeUser->id,
            'title' => 'Wrong Office Workflow',
            'message' => 'This linked record belongs to another office.',
            'notification_type' => 'WORKFLOW_UPDATE',
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $workflow['purchase_order']->purchase_order_id,
            'is_read' => false,
        ]);

        $this->actingAs($otherOfficeUser)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.data.0.target_url', null)
                ->where('notifications.data.0.target_label', null)
            );

        $this->actingAs($otherOfficeUser)
            ->get(route('notifications.show', $notification->notification_id))
            ->assertRedirect(route('notifications.index'));
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

    private function createUser(UserRole $role, ?Office $office = null): User
    {
        $office ??= $this->office;
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Notification test role',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }

    /**
     * @return array{supply_request: SupplyRequest, purchase_request: PurchaseRequest, purchase_order: PurchaseOrder, delivery: Delivery}
     */
    private function createProcurementWorkflowContext(Office $office, User $officeUser): array
    {
        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-NOTIF-'.uniqid(),
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(3)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Notification workflow target',
            'priority_level' => 'MEDIUM',
            'status' => 'FOR_PROCUREMENT',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'PROCESSING',
            'remarks' => null,
        ]);

        $supplier = Supplier::factory()->create();

        $purchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $this->adminUser->id,
            'po_number' => 'PO-NOTIF-'.uniqid(),
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 100,
            'terms_notes' => null,
            'remarks' => null,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-NOTIF-'.uniqid(),
            'purchase_order_id' => $purchaseOrder->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'actual_delivery_date' => null,
            'expected_arrival_date' => now()->addDays(7)->toDateString(),
            'received_by' => null,
            'receipt_status' => 'PENDING',
            'remarks' => null,
        ]);

        return [
            'supply_request' => $supplyRequest,
            'purchase_request' => $purchaseRequest,
            'purchase_order' => $purchaseOrder,
            'delivery' => $delivery,
        ];
    }
}
