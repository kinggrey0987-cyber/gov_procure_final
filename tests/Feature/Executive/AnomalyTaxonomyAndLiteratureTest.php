<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Models\Anomaly;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AnomalyDetectionService;
use App\Services\Analytics\AnomalyEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnomalyTaxonomyAndLiteratureTest extends TestCase
{
    use RefreshDatabase;

    protected Office $office;
    protected User $executive;
    protected User $gsoUser;
    protected ItemCategory $category;
    protected Item $item;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Statistical Research Office',
            'code' => 'SRO',
            'description' => 'Office for Statistical Validation',
            'is_active' => true,
        ]);

        $execRole = Role::query()->firstOrCreate(
            ['code' => UserRole::EXECUTIVE_OFFICER->value],
            ['name' => 'Executive Officer', 'is_active' => true]
        );
        $this->executive = User::factory()->withRole(UserRole::EXECUTIVE_OFFICER)->forOffice($this->office)->create([
            'role_id' => $execRole->id,
            'office_id' => $this->office->id,
            'emp_id' => 'EMP-EXEC-'.uniqid(),
        ]);

        $gsoRole = Role::query()->firstOrCreate(
            ['code' => UserRole::GSO_OFFICER->value],
            ['name' => 'GSO Officer', 'is_active' => true]
        );
        $this->gsoUser = User::factory()->withRole(UserRole::GSO_OFFICER)->forOffice($this->office)->create([
            'role_id' => $gsoRole->id,
            'office_id' => $this->office->id,
            'emp_id' => 'EMP-GSO-'.uniqid(),
        ]);

        $this->category = ItemCategory::query()->create([
            'category_name' => 'Office Consumables',
            'standard_delivery_days' => 7,
            'status' => 'active',
        ]);

        $this->item = Item::query()->create([
            'item_category_id' => $this->category->item_category_id,
            'item_name' => 'Statistical Bond Paper A4',
            'default_unit' => 'REAMS',
            'status' => 'active',
        ]);

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'Prime Paper Corporation',
            'contact_person' => 'Juan Dela Cruz',
            'email' => 'primepaper@example.com',
            'phone' => '09123456789',
            'address' => 'City Hall Complex',
            'status' => 'active',
        ]);
    }

    public function test_taxonomy_details_provides_comprehensive_mathematical_model_and_citations(): void
    {
        $types = Anomaly::availableTypes();

        $this->assertContains(Anomaly::TYPE_PRICE_Z_SCORE, $types);
        $this->assertContains(Anomaly::TYPE_EXCESSIVE_QUANTITY, $types);
        $this->assertContains(Anomaly::TYPE_FREQUENT_REQUEST_WINDOW, $types);
        $this->assertContains(Anomaly::TYPE_DELIVERY_DISCREPANCY, $types);

        // 1. Price Outlier (Grubbs 1969)
        $priceTax = Anomaly::taxonomyForType(Anomaly::TYPE_PRICE_Z_SCORE);
        $this->assertSame(Anomaly::CATEGORY_FINANCIAL_VARIANCE, $priceTax['category']);
        $this->assertStringContainsString('Grubbs', $priceTax['literature_citation']);
        $this->assertStringContainsString('Z = (X - μ) / σ', $priceTax['mathematical_model']);
        $this->assertStringContainsString('RA 9184', $priceTax['governance_mandate']);

        // 2. Excessive Quantity (Montgomery 2019 / Shewhart)
        $qtyTax = Anomaly::taxonomyForType(Anomaly::TYPE_EXCESSIVE_QUANTITY);
        $this->assertSame(Anomaly::CATEGORY_BEHAVIORAL_REQUISITION, $qtyTax['category']);
        $this->assertStringContainsString('Montgomery', $qtyTax['literature_citation']);
        $this->assertStringContainsString('μ + 2σ', $qtyTax['mathematical_model']);
        $this->assertStringContainsString('COA Circular', $qtyTax['governance_mandate']);

        // 3. Frequent Request Spike (World Bank 2020)
        $freqTax = Anomaly::taxonomyForType(Anomaly::TYPE_FREQUENT_REQUEST_WINDOW);
        $this->assertSame(Anomaly::CATEGORY_BEHAVIORAL_REQUISITION, $freqTax['category']);
        $this->assertStringContainsString('World Bank', $freqTax['literature_citation']);

        // 4. Delivery Discrepancy Rate (Montgomery 2019 / Quality Engineering)
        $deliveryTax = Anomaly::taxonomyForType(Anomaly::TYPE_DELIVERY_DISCREPANCY);
        $this->assertSame(Anomaly::CATEGORY_SUPPLY_CHAIN_INTEGRITY, $deliveryTax['category']);
        $this->assertStringContainsString('Defect Discrepancy Rate', $deliveryTax['mathematical_model']);
        $this->assertStringContainsString('Montgomery', $deliveryTax['literature_citation']);
    }

    /**
     * @return array{0: SupplyRequest, 1: SupplyRequestItem}
     */
    private function createSupplyRequestAndItem(): array
    {
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-STAT-'.uniqid(),
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(14)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Statistical testing request',
            'status' => 'APPROVED',
        ]);

        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);

        return [$sr, $sri];
    }

    private function createPurchaseOrder(PurchaseRequest $pr, SupplyRequest $sr, string $poNumber, string $status = 'APPROVED', float $totalAmount = 1000): PurchaseOrder
    {
        return PurchaseOrder::query()->create([
            'po_number' => $poNumber,
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'created_by' => $this->gsoUser->id,
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => $status,
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
            'total_amount' => $totalAmount,
        ]);
    }

    public function test_evidence_service_outputs_baseline_parameters_and_formal_literature(): void
    {
        [$sr, $sri] = $this->createSupplyRequestAndItem();

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-TEST-01',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po = $this->createPurchaseOrder($pr, $sr, 'PO-2026-TEST-01', 'APPROVED', 550.00);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 10,
            'unit' => 'REAMS',
            'unit_price' => 550.00,
            'total_price' => 5500.00,
        ]);

        $anomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_PRICE_Z_SCORE,
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $poItem->purchase_order_item_id,
            'item_id' => $this->item->item_id,
            'office_id' => $this->office->id,
            'detected_value' => 550.00,
            'expected_value' => 200.00,
            'threshold_value' => 400.00,
            'z_score' => 3.50,
            'severity' => 'HIGH',
            'detection_method' => 'Z_SCORE',
            'remarks' => 'Statistical Z-Score outlier (Z=3.50σ, N=12).',
        ]);

        $evidence = app(AnomalyEvidenceService::class)->build($anomaly);

        $this->assertArrayHasKey('baseline_parameters', $evidence);
        $this->assertEquals(200.00, $evidence['baseline_parameters']['mean']);
        $this->assertEquals(3.50, $evidence['baseline_parameters']['z_score']);
        $this->assertSame('99.73% (Critical 3σ Outlier)', $evidence['baseline_parameters']['confidence_level']);

        $this->assertArrayHasKey('taxonomy', $evidence);
        $this->assertSame(Anomaly::CATEGORY_FINANCIAL_VARIANCE, $evidence['taxonomy']['category']);

        $this->assertArrayHasKey('literature_citation', $evidence);
        $this->assertStringContainsString('Grubbs, F. E. (1969)', $evidence['literature_citation']);

        $this->assertArrayHasKey('governance_mandate', $evidence);
        $this->assertStringContainsString('RA 9184', $evidence['governance_mandate']);
    }

    public function test_detection_service_calculates_grubbs_z_score_parameters_for_price_variance(): void
    {
        $service = app(AnomalyDetectionService::class);
        [$sr, $sri] = $this->createSupplyRequestAndItem();

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-PRICE-01',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        // Baseline: 4 PO items at unit price 100
        for ($i = 1; $i <= 4; $i++) {
            $po = $this->createPurchaseOrder($pr, $sr, 'PO-BASE-00'.$i, 'COMPLETED', 1000);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $po->purchase_order_id,
                'supply_request_item_id' => $sri->supply_request_item_id,
                'item_id' => $this->item->item_id,
                'item_name_snapshot' => $this->item->item_name,
                'quantity_ordered' => 10,
                'unit' => 'REAMS',
                'unit_price' => 100.00,
                'total_price' => 1000.00,
            ]);
        }

        // Outlier PO item at unit price 350
        $outlierPo = $this->createPurchaseOrder($pr, $sr, 'PO-OUTLIER-001', 'APPROVED', 3500);

        $outlierItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $outlierPo->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 10,
            'unit' => 'REAMS',
            'unit_price' => 350.00,
            'total_price' => 3500.00,
        ]);

        $anomalies = $service->detectPriceZScoreAnomalies($this->executive);

        $priceAnomaly = $anomalies->firstWhere('item_id', $this->item->item_id);
        $this->assertNotNull($priceAnomaly, 'Expected price anomaly to be detected');

        $this->assertSame(Anomaly::TYPE_PRICE_Z_SCORE, $priceAnomaly->anomaly_type);
        $this->assertSame('Z_SCORE', $priceAnomaly->detection_method);
        $this->assertNotNull($priceAnomaly->z_score);
        $this->assertGreaterThanOrEqual(1.5, (float) $priceAnomaly->z_score);
        $this->assertStringContainsString('Grubbs', $priceAnomaly->remarks);
        $this->assertStringContainsString('Montgomery', $priceAnomaly->remarks);
    }

    public function test_detection_service_identifies_delivery_discrepancies(): void
    {
        $service = app(AnomalyDetectionService::class);
        [$sr, $sri] = $this->createSupplyRequestAndItem();

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-DEL-01',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po = $this->createPurchaseOrder($pr, $sr, 'PO-DISCREP-001', 'PARTIALLY_DELIVERED', 5000);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 100,
            'unit' => 'REAMS',
            'unit_price' => 50.00,
            'total_price' => 5000.00,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-10-DISC',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'PENDING_VERIFICATION',
            'batch_number' => 1,
            'total_batches' => 2,
            'delivery_schedule_type' => 'SCHEDULED_BATCH',
            'is_final_batch' => false,
        ]);

        // Ordered 100, received 60, damaged 10, missing 30 -> 40% defect/discrepancy rate
        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'expected_quantity' => 100,
            'received_quantity' => 60,
            'missing_quantity' => 30,
            'damaged_quantity' => 10,
            'item_condition' => 'DAMAGED',
            'remarks' => 'Damaged cartons and missing items during intake inspection',
        ]);

        $anomalies = $service->detectDeliveryDiscrepancyAnomalies($this->executive);

        $this->assertCount(1, $anomalies);
        $anomaly = $anomalies->first();

        $this->assertSame(Anomaly::TYPE_DELIVERY_DISCREPANCY, $anomaly->anomaly_type);
        $this->assertSame('INSPECTION_DEFECT_RATE', $anomaly->detection_method);
        $this->assertSame('HIGH', $anomaly->severity);
        $this->assertEquals(40.0, (float) $anomaly->detected_value);
        $this->assertEquals(0.0, (float) $anomaly->expected_value);
        $this->assertSame($this->item->item_id, $anomaly->item_id);
    }

    public function test_evidence_service_outputs_excessive_quantity_breakdown_and_derived_baseline_parameters(): void
    {
        [$sr, $sri] = $this->createSupplyRequestAndItem();

        $anomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_EXCESSIVE_QUANTITY,
            'reference_type' => SupplyRequest::class,
            'reference_id' => $sr->supply_request_id,
            'item_id' => $this->item->item_id,
            'office_id' => $this->office->id,
            'detected_value' => 120.00,
            'expected_value' => 25.00,
            'threshold_value' => 50.00,
            'z_score' => 3.12,
            'severity' => 'HIGH',
            'detection_method' => 'HISTORICAL_SPIKE_DETECTOR',
            'remarks' => 'Requested quantity exceeds office historical consumption velocity by 380%.',
        ]);

        $evidence = app(AnomalyEvidenceService::class)->build($anomaly);

        $this->assertSame('EXCESSIVE_QUANTITY', $evidence['type']);
        $this->assertArrayHasKey('baseline_parameters', $evidence);
        $this->assertEquals(25.00, $evidence['baseline_parameters']['mean']);
        $this->assertNotNull($evidence['baseline_parameters']['std_dev'], 'std_dev should not be null');
        $this->assertGreaterThan(0, $evidence['baseline_parameters']['std_dev']);
        $this->assertNotNull($evidence['baseline_parameters']['sample_size'], 'sample_size should not be null');
        $this->assertNotEmpty($evidence['series'], 'series should contain trend points');

        $this->assertArrayHasKey('plain_english_breakdown', $evidence);
        $this->assertNotEmpty($evidence['plain_english_breakdown']['what_was_recorded']);
        $this->assertNotEmpty($evidence['plain_english_breakdown']['what_is_typical']);
        $this->assertNotEmpty($evidence['plain_english_breakdown']['why_flagged']);
        $this->assertNotEmpty($evidence['plain_english_breakdown']['recommended_action']);
    }

    public function test_evidence_service_generates_plain_english_breakdown_for_price_anomaly(): void
    {
        [$sr, $sri] = $this->createSupplyRequestAndItem();
        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-PRICE-BRK',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);
        $po = $this->createPurchaseOrder($pr, $sr, 'PO-PRICE-BRK-1', 'APPROVED', 2450.00);
        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 1,
            'unit' => 'REAMS',
            'unit_price' => 2450.00,
            'total_price' => 2450.00,
        ]);

        $anomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_PRICE_Z_SCORE,
            'reference_type' => PurchaseOrderItem::class,
            'reference_id' => $poItem->purchase_order_item_id,
            'item_id' => $this->item->item_id,
            'office_id' => $this->office->id,
            'detected_value' => 2450.00,
            'expected_value' => 1850.00,
            'threshold_value' => 2100.00,
            'z_score' => 2.45,
            'severity' => 'MEDIUM',
            'detection_method' => 'STATISTICAL_Z_SCORE',
            'remarks' => 'Supplier quotation unit cost deviates +32.4% from historical average procurement benchmark.',
        ]);

        $evidence = app(AnomalyEvidenceService::class)->build($anomaly);

        $this->assertArrayHasKey('plain_english_breakdown', $evidence);
        $this->assertStringContainsString('2,450.00', $evidence['plain_english_breakdown']['what_was_recorded']);
        $this->assertStringContainsString('1,850.00', $evidence['plain_english_breakdown']['what_is_typical']);
        $this->assertNotEmpty($evidence['plain_english_breakdown']['why_flagged']);
        $this->assertNotEmpty($evidence['plain_english_breakdown']['recommended_action']);
    }
}

