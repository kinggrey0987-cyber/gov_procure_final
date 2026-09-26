<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Delivery\DeliveryService;
use App\Services\Inventory\DistributionRecordService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionCodeStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_distribution_service_generates_standard_format(): void
    {
        /** @var DistributionRecordService $service */
        $service = app(DistributionRecordService::class);

        $date = Carbon::parse('2026-09-15');
        $code1 = $service->generateNextDistributionCode($date);
        $this->assertSame('DIST-2026-09-0001', $code1);

        $gsoRole = Role::query()->firstOrCreate(
            ['code' => UserRole::GSO_OFFICER->value],
            ['name' => 'GSO Officer']
        );
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);
        $office = $gsoUser->office;

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-TEST-2026-09-0001',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Test distribution request',
            'priority_level' => 'NORMAL',
            'status' => 'PENDING',
        ]);

        DistributionRecord::query()->create([
            'distribution_code' => $code1,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'status' => 'DRAFT',
        ]);

        $code2 = $service->generateNextDistributionCode($date);
        $this->assertSame('DIST-2026-09-0002', $code2);

        // A different month starts from 0001
        $octDate = Carbon::parse('2026-10-01');
        $octCode = $service->generateNextDistributionCode($octDate);
        $this->assertSame('DIST-2026-10-0001', $octCode);
    }

    public function test_create_record_auto_assigns_standard_code(): void
    {
        /** @var DistributionRecordService $service */
        $service = app(DistributionRecordService::class);

        $gsoRole = Role::query()->firstOrCreate(
            ['code' => UserRole::GSO_OFFICER->value],
            ['name' => 'GSO Officer']
        );
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);
        $office = $gsoUser->office;

        $category = ItemCategory::query()->firstOrCreate(
            ['category_name' => 'Office Supplies'],
            ['description' => 'Supplies']
        );

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Ballpen',
            'default_unit' => 'piece',
            'status' => 'active',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'available_stock' => 100,
            'stock_on_hand' => 100,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 10,
            'last_updated' => now(),
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-TEST-2026-09-0001',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Test distribution request',
            'priority_level' => 'NORMAL',
            'status' => 'APPROVED',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 10,
            'unit' => 'piece',
        ]);

        $record = $service->createRecord($gsoUser, [
            'supply_request_id' => $supplyRequest->supply_request_id,
            'office_id' => $office->id,
            'release_date' => '2026-09-26',
        ]);

        $this->assertMatchesRegularExpression('/^DIST-2026-09-\d{4}$/', $record->distribution_code);
        $this->assertSame('DIST-2026-09-0001', $record->distribution_code);
    }

    public function test_delivery_service_generates_standard_format(): void
    {
        /** @var DeliveryService $service */
        $service = app(DeliveryService::class);

        $date = Carbon::parse('2026-09-15');
        $code = $service->generateNextDeliveryCode($date);
        $this->assertSame('DEL-2026-09-0001', $code);
    }
}
