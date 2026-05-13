<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StatusHistory;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AlertService;
use App\Services\Analytics\AnomalyService;
use App\Services\Analytics\PublicDisclosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExecutiveAnalyticsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected Office $officeA;
    protected Office $officeB;
    protected User $executive;
    protected User $officeUser;
    protected User $adminUser;
    protected User $gsoUser;
    protected User $procurementUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->officeA = $this->createOffice('Executive Office A', 'EXEC_A');
        $this->officeB = $this->createOffice('Executive Office B', 'EXEC_B');

        $this->executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->officeA);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->officeA);
        $this->adminUser = $this->createUser(UserRole::ADMIN, $this->officeA);
        $this->gsoUser = $this->createUser(UserRole::GSO_OFFICER, $this->officeA);
        $this->procurementUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->officeA);
    }

    public function test_exec_officer_can_access_anomaly_and_monitoring_routes(): void
    {
        $this->actingAs($this->executive)->get(route('executive.dashboard'))->assertOk();
        $this->actingAs($this->executive)->get(route('executive.anomalies.index'))->assertOk();
        $this->actingAs($this->executive)->get(route('executive.alerts.index'))->assertOk();
    }

    public function test_office_user_and_admin_cannot_access_executive_anomaly_routes(): void
    {
        $this->actingAs($this->officeUser)->get(route('executive.anomalies.index'))->assertForbidden();
        $this->actingAs($this->adminUser)->get(route('executive.anomalies.index'))->assertForbidden();
        $this->actingAs($this->officeUser)->get(route('executive.dashboard'))->assertForbidden();
        $this->actingAs($this->adminUser)->get(route('executive.dashboard'))->assertForbidden();
    }

    public function test_gso_and_proc_have_limited_alert_visibility_without_executive_anomaly_ownership(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9301);

        $alertService = app(AlertService::class);
        $alertService->create($this->executive, [
            'alert_type' => 'LOW_STOCK',
            'severity' => 'HIGH',
            'office_id' => $this->officeA->id,
            'alert_message' => 'Low stock operational alert',
        ]);

        $alertService->create($this->executive, [
            'alert_type' => 'PROCUREMENT_DELAY',
            'severity' => 'MEDIUM',
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'purchase_order_id' => $context['purchaseOrder']->purchase_order_id,
            'supplier_id' => $context['supplier']->supplier_id,
            'office_id' => $this->officeA->id,
            'alert_message' => 'Procurement delay alert',
        ]);

        $this->actingAs($this->gsoUser)
            ->get(route('inventory.alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('alerts.data', 1)
                ->where('permissions.review', false)
            );

        $this->actingAs($this->procurementUser)
            ->get(route('procurement.alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('alerts.data', 1));

        $this->actingAs($this->gsoUser)->get(route('executive.anomalies.index'))->assertForbidden();
        $this->actingAs($this->procurementUser)->get(route('executive.anomalies.index'))->assertForbidden();
    }

    public function test_procurement_analytics_uses_persisted_purchase_order_number_and_consistent_purchase_request_references(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9308);
        $purchaseRequest = PurchaseRequest::query()->findOrFail($context['purchaseOrder']->purchase_request_id);

        $context['purchaseOrder']->forceFill([
            'po_number' => 'PO-2026-00050',
            'created_at' => now()->addMinute(),
        ])->save();

        $purchaseRequest->forceFill([
            'created_at' => now(),
        ])->save();

        $this->actingAs($this->executive)
            ->get(route('executive.procurement-analytics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/ProcurementAnalytics/Index')
                ->where('recent.procurement_records.data.0.reference', 'PO-2026-00050')
                ->where('recent.procurement_records.data.0.details.linked_request', 'PR-'.str_pad((string) $purchaseRequest->purchase_request_id, 6, '0', STR_PAD_LEFT))
                ->where('recent.procurement_records.data.1.reference', 'PR-'.str_pad((string) $purchaseRequest->purchase_request_id, 6, '0', STR_PAD_LEFT))
            );
    }

    public function test_anomaly_and_alert_linkages_and_status_history_transitions_are_recorded(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9302);

        $anomalyService = app(AnomalyService::class);
        $anomaly = $anomalyService->create($this->executive, [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $context['purchaseOrderItem']->purchase_order_item_id,
            'item_id' => 9302,
            'office_id' => $this->officeA->id,
            'detected_value' => 500,
            'expected_value' => 200,
            'threshold_value' => 2,
            'z_score' => 3.1,
            'detection_method' => 'Z_SCORE',
            'severity' => 'HIGH',
            'remarks' => 'Outlier price detected.',
        ]);

        $alertService = app(AlertService::class);
        $alert = $alertService->create($this->executive, [
            'alert_type' => 'ANOMALY_DETECTED',
            'severity' => 'HIGH',
            'anomaly_id' => $anomaly->anomaly_id,
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'purchase_order_id' => $context['purchaseOrder']->purchase_order_id,
            'supplier_id' => $context['supplier']->supplier_id,
            'office_id' => $this->officeA->id,
            'alert_message' => 'Executive anomaly review required.',
        ]);

        $this->assertDatabaseHas('anomalies', [
            'anomaly_id' => $anomaly->anomaly_id,
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $context['purchaseOrderItem']->purchase_order_item_id,
            'item_id' => 9302,
            'office_id' => $this->officeA->id,
        ]);

        $this->assertDatabaseHas('alerts', [
            'alerts_id' => $alert->alerts_id,
            'anomaly_id' => $anomaly->anomaly_id,
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'purchase_order_id' => $context['purchaseOrder']->purchase_order_id,
            'supplier_id' => $context['supplier']->supplier_id,
            'office_id' => $this->officeA->id,
        ]);

        $this->actingAs($this->executive)
            ->put(route('executive.anomalies.update-status', $anomaly->anomaly_id), [
                'status' => 'IN_REVIEW',
                'remarks' => 'Reviewing now',
            ])
            ->assertRedirect();

        $this->actingAs($this->executive)
            ->put(route('executive.alerts.update-status', $alert->alerts_id), [
                'status' => 'IN_REVIEW',
                'remarks' => 'Executive review started',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('status_history', [
            'reference_type' => Anomaly::class,
            'reference_id' => $anomaly->anomaly_id,
            'new_status' => 'IN_REVIEW',
        ]);

        $this->assertDatabaseHas('status_history', [
            'reference_type' => Alert::class,
            'reference_id' => $alert->alerts_id,
            'new_status' => 'IN_REVIEW',
        ]);
    }

    public function test_disclosure_publish_writes_status_history_and_hides_unpublished_from_public(): void
    {
        $service = app(PublicDisclosureService::class);

        $draft = $service->createDraft($this->executive, [
            'title' => 'Procurement Summary',
            'type' => 'PROCUREMENT',
            'description' => 'Monthly procurement summary',
            'reference_type' => null,
            'reference_id' => null,
            'file_path' => '/files/proc-summary.pdf',
        ]);

        $service->publish($draft->fresh(), $this->executive, 'Approved for publication.');

        $this->assertDatabaseHas('public_disclosures', [
            'disclosure_id' => $draft->disclosure_id,
            'status' => 'PUBLISHED',
            'published_by' => $this->executive->id,
        ]);

        $this->assertDatabaseHas('status_history', [
            'reference_type' => PublicDisclosure::class,
            'reference_id' => $draft->disclosure_id,
            'new_status' => 'PUBLISHED',
        ]);

        $unpublished = PublicDisclosure::query()->create([
            'title' => 'Internal Draft',
            'type' => 'INTERNAL',
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Not for public release',
            'published_by' => null,
            'published_at' => null,
            'file_path' => null,
            'status' => 'DRAFT',
        ]);

        $publicUser = $this->createUser(UserRole::PUBLIC, $this->officeB);

        $this->actingAs($publicUser)->get(route('public.disclosures.index'))->assertOk();
        $this->actingAs($publicUser)
            ->get(route('public.disclosures.show', $unpublished->disclosure_id))
            ->assertNotFound();
    }

    public function test_invalid_anomaly_status_transition_is_rejected(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9303);

        $anomalyService = app(AnomalyService::class);
        $anomaly = $anomalyService->create($this->executive, [
            'anomaly_type' => 'EXCESSIVE_QUANTITY',
            'reference_type' => SupplyRequestItem::class,
            'reference_id' => $context['supplyRequestItem']->supply_request_item_id,
            'item_id' => 9303,
            'office_id' => $this->officeA->id,
            'severity' => 'MEDIUM',
            'detection_method' => 'MEAN_PLUS_2_SIGMA',
        ]);

        $anomalyService->updateStatus($anomaly, $this->executive, 'RESOLVED', 'Resolved quickly');

        $this->expectException(ValidationException::class);
        $anomalyService->updateStatus($anomaly->fresh(), $this->executive, 'IN_REVIEW', 'Invalid rollback');
    }

    public function test_invalid_alert_status_transition_is_rejected(): void
    {
        $alertService = app(AlertService::class);
        $alert = $alertService->create($this->executive, [
            'alert_type' => 'LOW_STOCK',
            'severity' => 'HIGH',
            'office_id' => $this->officeA->id,
            'alert_message' => 'Low stock warning',
        ]);

        $alertService->updateStatus($alert, $this->executive, 'RESOLVED', 'Handled');

        $this->expectException(ValidationException::class);
        $alertService->updateStatus($alert->fresh(), $this->executive, 'IN_REVIEW', 'Invalid rollback');
    }

    public function test_repeated_anomaly_generation_for_same_source_context_is_idempotent(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9304);

        $service = app(AnomalyService::class);
        $first = $service->create($this->executive, [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $context['purchaseOrderItem']->purchase_order_item_id,
            'item_id' => 9304,
            'office_id' => $this->officeA->id,
            'detection_method' => 'Z_SCORE',
            'severity' => 'HIGH',
        ]);

        $second = $service->create($this->executive, [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $context['purchaseOrderItem']->purchase_order_item_id,
            'item_id' => 9304,
            'office_id' => $this->officeA->id,
            'detection_method' => 'Z_SCORE',
            'severity' => 'HIGH',
        ]);

        $this->assertSame($first->anomaly_id, $second->anomaly_id);
        $this->assertSame(1, Anomaly::query()->count());
    }

    public function test_exec_officer_only_can_review_anomaly_status(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9305);

        $anomaly = app(AnomalyService::class)->create($this->executive, [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $context['purchaseOrderItem']->purchase_order_item_id,
            'item_id' => 9305,
            'office_id' => $this->officeA->id,
            'detection_method' => 'Z_SCORE',
            'severity' => 'HIGH',
        ]);

        $this->actingAs($this->gsoUser)
            ->put(route('executive.anomalies.update-status', $anomaly->anomaly_id), [
                'status' => 'IN_REVIEW',
            ])
            ->assertForbidden();
    }

    public function test_gso_cannot_open_alert_detail_pages_under_executive_owned_review(): void
    {
        $alertService = app(AlertService::class);
        $allowed = $alertService->create($this->executive, [
            'alert_type' => 'LOW_STOCK',
            'severity' => 'HIGH',
            'office_id' => $this->officeA->id,
            'alert_message' => 'Low stock',
        ]);
        $blocked = $alertService->create($this->executive, [
            'alert_type' => 'PROCUREMENT_DELAY',
            'severity' => 'MEDIUM',
            'office_id' => $this->officeA->id,
            'alert_message' => 'Procurement delayed',
        ]);

        $this->actingAs($this->gsoUser)
            ->get(route('inventory.alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('alerts.data', 1));

        $this->actingAs($this->gsoUser)
            ->get(route('inventory.alerts.show', $allowed->alerts_id))
            ->assertForbidden();

        $this->actingAs($this->gsoUser)
            ->get(route('inventory.alerts.show', $blocked->alerts_id))
            ->assertForbidden();
    }

    public function test_procurement_cannot_open_alert_detail_pages_under_executive_owned_review(): void
    {
        $alertService = app(AlertService::class);
        $allowed = $alertService->create($this->executive, [
            'alert_type' => 'PROCUREMENT_DELAY',
            'severity' => 'MEDIUM',
            'office_id' => $this->officeA->id,
            'alert_message' => 'Procurement delayed',
        ]);
        $blocked = $alertService->create($this->executive, [
            'alert_type' => 'LOW_STOCK',
            'severity' => 'HIGH',
            'office_id' => $this->officeA->id,
            'alert_message' => 'Low stock',
        ]);

        $this->actingAs($this->procurementUser)
            ->get(route('procurement.alerts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('alerts.data', 1));

        $this->actingAs($this->procurementUser)
            ->get(route('procurement.alerts.show', $allowed->alerts_id))
            ->assertForbidden();

        $this->actingAs($this->procurementUser)
            ->get(route('procurement.alerts.show', $blocked->alerts_id))
            ->assertForbidden();
    }

    public function test_anomaly_detail_exposes_evidence_payload_for_price_z_score(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9310);

        $anomaly = app(AnomalyService::class)->create($this->executive, [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $context['purchaseOrderItem']->purchase_order_item_id,
            'item_id' => 9310,
            'office_id' => $this->officeA->id,
            'detected_value' => 250,
            'expected_value' => 100,
            'threshold_value' => 2,
            'z_score' => 3.25,
            'detection_method' => 'Z_SCORE',
            'severity' => 'HIGH',
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.anomalies.show', $anomaly->anomaly_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('evidence.type', 'PRICE_Z_SCORE')
                ->where('evidence.metrics.z_score', 3.25)
                ->has('evidence.comparison', 1)
            );
    }

    public function test_alert_detail_exposes_linked_anomaly_evidence_payload(): void
    {
        $context = $this->createSupplyAndOrderContext($this->officeA, $this->procurementUser, $this->officeUser, 9311);

        $anomaly = app(AnomalyService::class)->create($this->executive, [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $context['purchaseOrderItem']->purchase_order_item_id,
            'item_id' => 9311,
            'office_id' => $this->officeA->id,
            'detected_value' => 220,
            'expected_value' => 100,
            'threshold_value' => 2,
            'z_score' => 2.9,
            'detection_method' => 'Z_SCORE',
            'severity' => 'HIGH',
        ]);

        $alert = app(AlertService::class)->create($this->executive, [
            'alert_type' => 'ANOMALY_DETECTED',
            'severity' => 'HIGH',
            'anomaly_id' => $anomaly->anomaly_id,
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'purchase_order_id' => $context['purchaseOrder']->purchase_order_id,
            'supplier_id' => $context['supplier']->supplier_id,
            'office_id' => $this->officeA->id,
            'alert_message' => 'Linked anomaly evidence should be visible.',
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.alerts.show', $alert->alerts_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('alert.anomaly_id', $anomaly->anomaly_id)
                ->where('anomaly_evidence.type', 'PRICE_Z_SCORE')
                ->has('anomaly_evidence.comparison', 1)
            );
    }

    public function test_alerts_cannot_be_reviewed_by_unauthorized_roles_and_status_writes_audit(): void
    {
        $alertService = app(AlertService::class);
        $alert = $alertService->create($this->executive, [
            'alert_type' => 'ANOMALY_DETECTED',
            'severity' => 'HIGH',
            'office_id' => $this->officeA->id,
            'alert_message' => 'Executive review required',
        ]);

        $this->actingAs($this->gsoUser)
            ->put(route('executive.alerts.update-status', $alert->alerts_id), [
                'status' => 'IN_REVIEW',
            ])
            ->assertForbidden();

        $this->actingAs($this->executive)
            ->put(route('executive.alerts.update-status', $alert->alerts_id), [
                'status' => 'IN_REVIEW',
                'remarks' => 'Started executive review',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'reference_type' => Alert::class,
            'reference_id' => $alert->alerts_id,
            'action_type' => 'UPDATE_STATUS',
            'module_name' => 'Executive.Alert',
        ]);
    }

    public function test_disclosure_publish_status_is_consistent_and_repollish_is_blocked(): void
    {
        $service = app(PublicDisclosureService::class);

        $draft = $service->createDraft($this->executive, [
            'title' => 'Disclosure Consistency',
            'type' => 'PROCUREMENT',
            'description' => 'Consistency check',
        ]);

        $this->assertNull($draft->published_at);
        $this->assertSame('DRAFT', $draft->status);

        $published = $service->publish($draft->fresh(), $this->executive, 'Publishing draft');
        $this->assertSame('PUBLISHED', $published->status);
        $this->assertNotNull($published->published_at);

        $this->expectException(ValidationException::class);
        $service->publish($published->fresh(), $this->executive, 'Second publish attempt');
    }

    /**
     * @return array<string, mixed>
     */
    private function createSupplyAndOrderContext(Office $office, User $procurementUser, User $officeUser, int $itemId): array
    {
        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 1000,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $itemId,
            'item_category_id' => 1,
            'quantity' => 5,
            'unit_cost' => 100,
            'estimated_budget' => 500,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-EXEC-'.uniqid(),
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Executive analytics context',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $supplyRequestItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => $itemId,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Item '.$itemId,
            'quantity_requested' => 5,
        ]);

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procurementUser->id,
            'status' => 'PROCESSING',
            'remarks' => 'Executive analytics PR',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Exec Supplier '.uniqid(),
            'contact_person' => 'Contact',
            'email' => uniqid('exec-supplier', true).'@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procurementUser->id,
            'po_number' => 'PO-EXEC-'.uniqid(),
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'total_amount' => 500,
            'terms_notes' => 'Net 30',
            'remarks' => null,
        ]);

        $purchaseOrderItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $purchaseOrder->purchase_order_id,
            'supply_request_item_id' => $supplyRequestItem->supply_request_item_id,
            'item_id' => $itemId,
            'item_name_snapshot' => 'Item '.$itemId,
            'specification' => 'Executive analytics test item',
            'quantity_ordered' => 5,
            'unit' => 'PCS',
            'unit_price' => 100,
            'total_price' => 500,
        ]);

        return [
            'supplyRequest' => $supplyRequest,
            'supplyRequestItem' => $supplyRequestItem,
            'purchaseOrder' => $purchaseOrder,
            'purchaseOrderItem' => $purchaseOrderItem,
            'supplier' => $supplier,
        ];
    }

    private function createOffice(string $name, string $code): Office
    {
        return Office::query()->create([
            'name' => $name,
            'code' => $code,
            'description' => $name.' description',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for executive analytics tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
