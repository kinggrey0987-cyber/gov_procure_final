<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AnomalyDetectionService;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PpmpAnomalyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected User $executive;
    protected User $officeUser;
    protected User $adminUser;
    protected User $gsoUser;
    protected User $procurementUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->office = $this->createOffice('PPMP Anomaly Office', 'PPAO');

        $this->executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $this->office);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);
        $this->adminUser = $this->createUser(UserRole::ADMIN, $this->office);
        $this->gsoUser = $this->createUser(UserRole::GSO_OFFICER, $this->office);
        $this->procurementUser = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office);
    }

    public function test_ppmp_over_request_anomaly_is_generated_with_valid_detected_expected_values(): void
    {
        $context = $this->createPpmpOverRequestContext(9701, 10, [6, 6]);

        $detected = app(AnomalyDetectionService::class)->runExecutiveDetections($this->executive);

        $this->assertTrue($detected->pluck('anomaly_type')->contains('PPMP_OVER_REQUEST'));

        $anomaly = Anomaly::query()
            ->where('anomaly_type', 'PPMP_OVER_REQUEST')
            ->where('reference_type', SupplyRequestItem::class)
            ->where('item_id', $context['item']->item_id)
            ->firstOrFail();

        $this->assertSame('PPMP_PLAN_CONSUMPTION', $anomaly->detection_method);
        $this->assertNotNull($anomaly->detected_value);
        $this->assertNotNull($anomaly->expected_value);
        $this->assertGreaterThan((float) $anomaly->expected_value, (float) $anomaly->detected_value);
        $this->assertSame($this->office->id, $anomaly->office_id);
    }

    public function test_ppmp_anomaly_generation_is_idempotent_and_detection_alerts_are_not_duplicated(): void
    {
        $context = $this->createPpmpOverRequestContext(9702, 10, [6, 6]);

        $this->actingAs($this->executive)
            ->post(route('executive.anomalies.detect'))
            ->assertRedirect(route('executive.anomalies.index'));

        $this->actingAs($this->executive)
            ->post(route('executive.anomalies.detect'))
            ->assertRedirect(route('executive.anomalies.index'));

        $this->assertSame(
            1,
            Anomaly::query()
                ->where('anomaly_type', 'PPMP_OVER_REQUEST')
                ->where('item_id', $context['item']->item_id)
                ->count(),
        );

        $this->assertSame(
            1,
            Alert::query()
                ->where('alert_type', 'ANOMALY_DETECTED')
                ->whereHas('anomaly', function ($query) use ($context): void {
                    $query->where('anomaly_type', 'PPMP_OVER_REQUEST')
                        ->where('item_id', $context['item']->item_id);
                })
                ->count(),
        );
    }

    public function test_ppmp_anomalies_are_exposed_only_in_exec_routes(): void
    {
        $this->createPpmpOverRequestContext(9703, 10, [7, 6]);
        app(AnomalyDetectionService::class)->runExecutiveDetections($this->executive);

        $this->actingAs($this->executive)
            ->get(route('executive.anomalies.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/Monitoring/AnomaliesAlerts')
                ->has('recent.anomalies')
            );

        $this->actingAs($this->executive)
            ->get(route('executive.anomalies-monitoring'))
            ->assertOk();
    }

    public function test_soft_limit_over_request_submission_is_visible_in_exec_anomaly_monitoring_without_manual_detection_run(): void
    {
        $context = $this->createApprovedPpmpRequestContext(9705, 8);

        $createResponse = $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.store'), [
                'ppmp_id' => $context['ppmp']->ppmp_id,
                'needed_by_date' => now()->addDays(3)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Urgent office need beyond PPMP allocation',
                'priority_level' => 'URGENT',
                'review_notes' => 'Soft-limit submission',
                'items' => [[
                    'item_id' => $context['item']->item_id,
                    'ppmp_item_id' => $context['ppmpItem']->ppmp_item_id,
                    'item_name_snapshot' => $context['item']->item_name,
                    'quantity_requested' => 11,
                ]],
            ]);

        $createResponse->assertRedirect();

        $supplyRequest = SupplyRequest::query()->latest('supply_request_id')->firstOrFail();

        $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.submit', $supplyRequest->supply_request_id), [
                'review_notes' => 'Submit urgent request',
            ])
            ->assertRedirect(route('office.supply-requests.show', $supplyRequest->supply_request_id));

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'PPMP_OVER_REQUEST',
            'office_id' => $this->office->id,
            'detection_method' => 'PPMP_PLAN_CONSUMPTION',
            'status' => 'OPEN',
        ]);

        $anomaly = Anomaly::query()
            ->where('anomaly_type', 'PPMP_OVER_REQUEST')
            ->where('office_id', $this->office->id)
            ->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->executive->id,
            'notification_type' => 'EXEC_ANOMALY_DETECTED',
            'reference_type' => Anomaly::class,
            'reference_id' => $anomaly->anomaly_id,
            'is_read' => false,
        ]);

        $this->actingAs($this->executive)
            ->get(route('executive.anomalies-monitoring'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Executive/Monitoring/AnomaliesAlerts')
                ->has('recent.anomalies')
            );
    }

    public function test_early_depletion_submission_is_visible_to_exec_without_manual_detection_run(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-03-15');
        $context = $this->createApprovedPpmpRequestContext(9706, 100);

        foreach ([20, 30] as $quantity) {
            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => 'SR-PPA-'.uniqid(),
                'office_id' => $this->office->id,
                'requested_by' => $this->officeUser->id,
                'ppmp_id' => $context['ppmp']->ppmp_id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Earlier PPMP consumption',
                'priority_level' => 'HIGH',
                'status' => 'SUBMITTED',
                'review_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $supplyRequest->supply_request_id,
                'item_id' => $context['item']->item_id,
                'ppmp_item_id' => $context['ppmpItem']->ppmp_item_id,
                'item_name_snapshot' => $context['item']->item_name,
                'quantity_requested' => $quantity,
            ]);
        }

        $createResponse = $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.store'), [
                'ppmp_id' => $context['ppmp']->ppmp_id,
                'needed_by_date' => now()->addDays(10)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Consumption reaches early depletion threshold',
                'priority_level' => 'HIGH',
                'review_notes' => 'Early depletion submission',
                'items' => [[
                    'item_id' => $context['item']->item_id,
                    'ppmp_item_id' => $context['ppmpItem']->ppmp_item_id,
                    'item_name_snapshot' => $context['item']->item_name,
                    'quantity_requested' => 36,
                ]],
            ]);

        $createResponse->assertRedirect();

        $supplyRequest = SupplyRequest::query()->latest('supply_request_id')->firstOrFail();

        $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.submit', $supplyRequest->supply_request_id), [
                'review_notes' => 'Submit early depletion request',
            ])
            ->assertRedirect(route('office.supply-requests.show', $supplyRequest->supply_request_id));

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'PPMP_EARLY_DEPLETION',
            'office_id' => $this->office->id,
            'detection_method' => 'PPMP_EARLY_DEPLETION_WINDOW',
            'detected_value' => 86,
            'expected_value' => 50,
            'threshold_value' => 85,
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseMissing('anomalies', [
            'anomaly_type' => 'PPMP_OVER_REQUEST',
            'office_id' => $this->office->id,
            'item_id' => $context['item']->item_id,
        ]);

        $anomaly = Anomaly::query()
            ->where('anomaly_type', 'PPMP_EARLY_DEPLETION')
            ->where('office_id', $this->office->id)
            ->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->executive->id,
            'notification_type' => 'EXEC_ANOMALY_DETECTED',
            'reference_type' => Anomaly::class,
            'reference_id' => $anomaly->anomaly_id,
            'is_read' => false,
        ]);
    }

    public function test_remaining_supply_request_anomalies_are_created_on_submission_without_manual_detection_run(): void
    {
        $context = $this->createApprovedPpmpRequestContext(9707, 1000);
        $otherOffice = $this->createOffice('PPMP Anomaly Other Office', 'PPAO2');
        $otherOfficeUser = $this->createUser(UserRole::OFFICE_USER, $otherOffice);

        $secondItem = Item::query()->create([
            'item_category_id' => $context['item']->item_category_id,
            'item_name' => 'Approved PPMP Secondary Item 9707',
            'description' => null,
            'default_unit' => 'PCS',
            'status' => 'active',
        ]);

        $secondPpmpItem = PpmpItem::query()->create([
            'ppmp_id' => $context['ppmp']->ppmp_id,
            'item_id' => $secondItem->item_id,
            'item_category_id' => $context['item']->item_category_id,
            'quantity' => 1000,
            'unit_cost' => 50,
            'estimated_budget' => 50000,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        for ($index = 0; $index < 9; $index++) {
            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => 'SR-PPA-'.uniqid(),
                'office_id' => $this->office->id,
                'requested_by' => $this->officeUser->id,
                'ppmp_id' => $context['ppmp']->ppmp_id,
                'request_date' => now()->subDays($index + 1)->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Historical request volume sample',
                'priority_level' => 'HIGH',
                'status' => 'SUBMITTED',
                'review_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $supplyRequest->supply_request_id,
                'item_id' => $context['item']->item_id,
                'ppmp_item_id' => $context['ppmpItem']->ppmp_item_id,
                'item_name_snapshot' => $context['item']->item_name,
                'quantity_requested' => 5,
            ]);
        }

        $otherItemRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-PPA-'.uniqid(),
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $context['ppmp']->ppmp_id,
            'request_date' => now()->subDay()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Other PPMP item request sample',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $otherItemRequest->supply_request_id,
            'item_id' => $secondItem->item_id,
            'ppmp_item_id' => $secondPpmpItem->ppmp_item_id,
            'item_name_snapshot' => $secondItem->item_name,
            'quantity_requested' => 5,
        ]);

        SupplyRequest::query()->create([
            'request_code' => 'SR-PPA-'.uniqid(),
            'office_id' => $otherOffice->id,
            'requested_by' => $otherOfficeUser->id,
            'ppmp_id' => null,
            'request_date' => now()->subDay()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Cross-office frequency baseline',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.store'), [
                'ppmp_id' => $context['ppmp']->ppmp_id,
                'needed_by_date' => now()->addDays(10)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'Submission triggers remaining SR anomaly algorithms',
                'priority_level' => 'HIGH',
                'review_notes' => 'Automatic anomaly submission',
                'items' => [[
                    'item_id' => $context['item']->item_id,
                    'ppmp_item_id' => $context['ppmpItem']->ppmp_item_id,
                    'item_name_snapshot' => $context['item']->item_name,
                    'quantity_requested' => 50,
                ]],
            ])
            ->assertRedirect();

        $supplyRequest = SupplyRequest::query()->latest('supply_request_id')->firstOrFail();

        $this->actingAs($this->officeUser)
            ->post(route('office.supply-requests.submit', $supplyRequest->supply_request_id), [
                'review_notes' => 'Submit automatic anomaly request',
            ])
            ->assertRedirect(route('office.supply-requests.show', $supplyRequest->supply_request_id));

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'FREQUENT_REQUEST_WINDOW',
            'office_id' => $this->office->id,
            'detection_method' => 'SLIDING_WINDOW_30D',
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'EXCESSIVE_QUANTITY',
            'office_id' => $this->office->id,
            'item_id' => $context['item']->item_id,
            'detection_method' => 'MEAN_PLUS_2_SIGMA',
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'PPMP_FREQUENCY_IRREGULARITY',
            'office_id' => $this->office->id,
            'item_id' => $context['item']->item_id,
            'detection_method' => 'PPMP_SLIDING_WINDOW_30D',
            'status' => 'OPEN',
        ]);
    }

    public function test_non_exec_roles_are_denied_from_exec_ppmp_anomaly_pages(): void
    {
        $this->createPpmpOverRequestContext(9704, 10, [7, 6]);
        app(AnomalyDetectionService::class)->runExecutiveDetections($this->executive);

        foreach ([$this->officeUser, $this->adminUser, $this->gsoUser, $this->procurementUser] as $user) {
            $this->actingAs($user)
                ->get(route('executive.anomalies.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('executive.anomalies-monitoring'))
                ->assertForbidden();
        }
    }

    public function test_price_anomaly_is_generated_only_from_procurement_side_purchase_order_items(): void
    {
        $context = $this->createProcurementPriceAnomalyContext(9801, [100, 101, 102, 103, 104, 260]);

        app(AnomalyDetectionService::class)->runExecutiveDetections($this->executive);

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'item_id' => $context['item']->item_id,
            'office_id' => $this->office->id,
        ]);

        $this->assertDatabaseMissing('anomalies', [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => SupplyRequestItem::class,
            'item_id' => $context['item']->item_id,
        ]);
    }

    public function test_price_anomaly_is_visible_to_exec_when_procurement_encodes_outlier_po_price(): void
    {
        $context = $this->createProcurementPriceHistoryContext(9803, [100, 101, 102, 103, 104]);

        $purchaseOrderItem = app(PurchaseOrderService::class)->addItem(
            $context['purchaseOrder'],
            $this->procurementUser,
            [
                'supply_request_item_id' => $context['supplyRequestItem']->supply_request_item_id,
                'unit' => 'PCS',
                'unit_price' => 260,
            ],
        );

        $this->assertDatabaseHas('anomalies', [
            'anomaly_type' => 'PRICE_Z_SCORE',
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $purchaseOrderItem->purchase_order_item_id,
            'item_id' => $context['item']->item_id,
            'office_id' => $this->office->id,
            'detection_method' => 'Z_SCORE',
            'status' => 'OPEN',
        ]);

        $anomaly = Anomaly::query()
            ->where('anomaly_type', 'PRICE_Z_SCORE')
            ->where('reference_id', $purchaseOrderItem->purchase_order_item_id)
            ->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->executive->id,
            'notification_type' => 'EXEC_ANOMALY_DETECTED',
            'reference_type' => Anomaly::class,
            'reference_id' => $anomaly->anomaly_id,
            'is_read' => false,
        ]);
    }

    public function test_stock_fulfilled_requests_without_procurement_events_do_not_trigger_price_anomaly(): void
    {
        $this->createPpmpOverRequestContext(9802, 20, [3, 4, 5]);

        app(AnomalyDetectionService::class)->runExecutiveDetections($this->executive);

        $this->assertDatabaseMissing('anomalies', [
            'anomaly_type' => 'PRICE_Z_SCORE',
        ]);
    }

    /**
     * @param  array<int, float|int>  $requestQuantities
     * @return array<string, mixed>
     */
    private function createPpmpOverRequestContext(int $itemId, float $plannedQuantity, array $requestQuantities): array
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'PPMP Anomaly Category '.$itemId,
            'standard_delivery_days' => 5,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'PPMP Anomaly Item '.$itemId,
            'description' => null,
            'default_unit' => 'PCS',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 10000,
            'date_prepared' => now()->startOfYear()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => $plannedQuantity,
            'unit_cost' => 50,
            'estimated_budget' => $plannedQuantity * 50,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        $sequence = 0;
        foreach ($requestQuantities as $quantity) {
            $sequence++;

            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => 'SR-PPA-'.uniqid(),
                'office_id' => $this->office->id,
                'requested_by' => $this->officeUser->id,
                'ppmp_id' => $ppmp->ppmp_id,
                'request_date' => now()->startOfYear()->addDays(15 + $sequence)->toDateString(),
                'needed_by_date' => now()->startOfYear()->addDays(30 + $sequence)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => 'PPMP anomaly test request',
                'priority_level' => 'HIGH',
                'status' => 'SUBMITTED',
                'review_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $supplyRequest->supply_request_id,
                'item_id' => $item->item_id,
                'ppmp_item_id' => $ppmpItem->ppmp_item_id,
                'item_name_snapshot' => $item->item_name,
                'quantity_requested' => $quantity,
            ]);
        }

        return [
            'item' => $item,
            'ppmp' => $ppmp,
            'ppmpItem' => $ppmpItem,
        ];
    }

    /**
     * @param  array<int, float|int>  $unitPrices
     * @return array<string, mixed>
     */
    private function createProcurementPriceAnomalyContext(int $itemId, array $unitPrices): array
    {
        $context = $this->createPpmpOverRequestContext(
            $itemId,
            100,
            array_fill(0, max(3, count($unitPrices)), 2)
        );
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Price Anomaly Supplier '.$itemId,
            'contact_person' => 'Supplier Contact',
            'email' => 'price-anomaly-'.$itemId.'@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $supplyItems = SupplyRequestItem::query()
            ->where('item_id', $context['item']->item_id)
            ->with('supplyRequest:supply_request_id,office_id')
            ->orderBy('supply_request_item_id')
            ->get();

        foreach ($unitPrices as $index => $unitPrice) {
            $supplyItem = $supplyItems->get($index);
            if (! $supplyItem instanceof SupplyRequestItem) {
                continue;
            }

            $purchaseRequest = PurchaseRequest::query()->create([
                'supply_request_id' => $supplyItem->supply_request_id,
                'office_id' => $this->office->id,
                'requested_by' => $this->procurementUser->id,
                'status' => 'PROCESSING',
                'remarks' => 'Procurement price anomaly test PR',
            ]);

            $purchaseOrder = PurchaseOrder::query()->create([
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supply_request_id' => $supplyItem->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
                'created_by' => $this->procurementUser->id,
                'po_number' => 'PO-PPA-'.$itemId.'-'.$index,
                'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
                'status' => 'PLACED',
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'total_amount' => (float) $unitPrice,
                'terms_notes' => 'Net 30',
                'remarks' => 'Price anomaly testing PO',
            ]);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $purchaseOrder->purchase_order_id,
                'supply_request_item_id' => $supplyItem->supply_request_item_id,
                'item_id' => $context['item']->item_id,
                'item_name_snapshot' => $context['item']->item_name,
                'specification' => 'Procurement price sample',
                'quantity_ordered' => 1,
                'unit' => 'PCS',
                'unit_price' => (float) $unitPrice,
                'total_price' => (float) $unitPrice,
            ]);
        }

        return $context;
    }

    /**
     * @param  array<int, float|int>  $unitPrices
     * @return array<string, mixed>
     */
    private function createProcurementPriceHistoryContext(int $itemId, array $unitPrices): array
    {
        $context = $this->createPpmpOverRequestContext(
            $itemId,
            100,
            array_fill(0, count($unitPrices) + 1, 2)
        );

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Auto Price Anomaly Supplier '.$itemId,
            'contact_person' => 'Supplier Contact',
            'email' => 'auto-price-anomaly-'.$itemId.'@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $supplyItems = SupplyRequestItem::query()
            ->where('item_id', $context['item']->item_id)
            ->with('supplyRequest:supply_request_id,office_id')
            ->orderBy('supply_request_item_id')
            ->get();

        foreach ($unitPrices as $index => $unitPrice) {
            $supplyItem = $supplyItems->get($index);
            if (! $supplyItem instanceof SupplyRequestItem) {
                continue;
            }

            $purchaseRequest = PurchaseRequest::query()->create([
                'supply_request_id' => $supplyItem->supply_request_id,
                'office_id' => $this->office->id,
                'requested_by' => $this->procurementUser->id,
                'status' => 'PROCESSING',
                'remarks' => 'Procurement price history test PR',
            ]);

            $purchaseOrder = PurchaseOrder::query()->create([
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supply_request_id' => $supplyItem->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
                'created_by' => $this->procurementUser->id,
                'po_number' => 'PO-APPA-'.$itemId.'-'.$index,
                'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
                'status' => 'PLACED',
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'total_amount' => (float) $unitPrice,
                'terms_notes' => 'Net 30',
                'remarks' => 'Price history testing PO',
            ]);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $purchaseOrder->purchase_order_id,
                'supply_request_item_id' => $supplyItem->supply_request_item_id,
                'item_id' => $context['item']->item_id,
                'item_name_snapshot' => $context['item']->item_name,
                'specification' => 'Procurement price history sample',
                'quantity_ordered' => 1,
                'unit' => 'PCS',
                'unit_price' => (float) $unitPrice,
                'total_price' => (float) $unitPrice,
            ]);
        }

        $latestSupplyItem = $supplyItems->last();

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $latestSupplyItem->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->procurementUser->id,
            'status' => 'PROCESSING',
            'remarks' => 'Procurement outlier price test PR',
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $latestSupplyItem->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $this->procurementUser->id,
            'po_number' => 'PO-APPA-'.$itemId.'-latest',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'DRAFT',
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 0,
            'terms_notes' => 'Net 30',
            'remarks' => 'Outlier price testing PO',
        ]);

        return [
            ...$context,
            'purchaseOrder' => $purchaseOrder,
            'supplyRequestItem' => $latestSupplyItem,
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

    /**
     * @return array<string, mixed>
     */
    private function createApprovedPpmpRequestContext(int $itemId, float $plannedQuantity): array
    {
        $category = ItemCategory::query()->create([
            'category_name' => 'Approved PPMP Category '.$itemId,
            'standard_delivery_days' => 5,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Approved PPMP Item '.$itemId,
            'description' => null,
            'default_unit' => 'PCS',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->officeUser->id,
            'approved_by' => $this->executive->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'total_estimated_budget' => 10000,
            'date_prepared' => now()->startOfYear()->toDateString(),
            'date_approved' => now()->toDateString(),
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => $plannedQuantity,
            'unit_cost' => 50,
            'estimated_budget' => $plannedQuantity * 50,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        return [
            'item' => $item,
            'ppmp' => $ppmp,
            'ppmpItem' => $ppmpItem,
        ];
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for PPMP anomaly integration tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'emp_id' => sprintf('PPA-%d-%s', $office->id, strtoupper(substr((string) str()->uuid(), 0, 8))),
        ]);
    }
}
