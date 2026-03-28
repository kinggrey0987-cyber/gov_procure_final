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
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AnomalyDetectionService;
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
            'fund_source' => 'General Fund',
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
                'estimated_unit_cost' => 50,
                'estimated_total_cost' => ((float) $quantity) * 50,
            ]);
        }

        return [
            'item' => $item,
            'ppmp' => $ppmp,
            'ppmpItem' => $ppmpItem,
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
                'description' => 'Role for PPMP anomaly integration tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
