<?php

namespace Tests\Feature\Office;

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Office\PpmpService;
use App\Services\Office\SupplyRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FiscalYearIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_past_year_ppmps_are_excluded_from_supply_request_options(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        // Create a past year PPMP (e.g. 2024) — CLOSED
        Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'fiscal_year' => 2024,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'CLOSED',
            'date_prepared' => '2024-01-15',
            'total_estimated_budget' => 50000,
        ]);

        // Create current year approved PPMP (2026)
        $currentPpmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'fiscal_year' => (int) now()->format('Y'),
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'date_prepared' => now()->toDateString(),
            'date_approved' => now()->toDateString(),
            'total_estimated_budget' => 50000,
        ]);

        $service = app(SupplyRequestService::class);
        $options = $service->ppmpOptionsForOffice($office->id);

        $optionIds = collect($options)->pluck('ppmp_id')->all();

        $this->assertContains((int) $currentPpmp->ppmp_id, $optionIds);
        // Past year PPMP must not appear in options
        $this->assertCount(1, $optionIds, 'Only the current fiscal year PPMP should appear in options.');
    }

    public function test_supply_request_rejects_past_year_ppmp_reference(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $category = ItemCategory::query()->create([
            'category_name' => 'Test Supplies',
            'standard_delivery_days' => 7,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Test Paper',
            'description' => null,
            'default_unit' => 'PCS',
            'status' => 'active',
        ]);

        // Past year PPMP — status APPROVED but fiscal_year is 2024
        $pastPpmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'fiscal_year' => 2024,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'APPROVED',
            'date_prepared' => '2024-01-15',
            'total_estimated_budget' => 50000,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $pastPpmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 10,
            'unit_cost' => 100,
            'estimated_budget' => 1000,
            'procurement_schedule' => 'Q1',
            'remarks' => 'Test',
        ]);

        $response = $this->actingAs($officeUser)->post(route('office.supply-requests.store'), [
            'ppmp_id' => $pastPpmp->ppmp_id,
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Testing past PPMP reference',
            'priority_level' => 'NORMAL',
            'items' => [
                [
                    'ppmp_item_id' => $ppmpItem->ppmp_item_id,
                    'quantity_requested' => 5,
                ],
            ],
        ]);

        // The StoreSupplyRequestRequest validates fiscal_year === currentYear.
        // A past-year PPMP should fail validation and redirect back with errors.
        $response->assertRedirect();
        $response->assertSessionHasErrors('ppmp_id');
    }

    public function test_future_fiscal_year_ppmp_cannot_be_submitted_for_approval(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'standard_delivery_days' => 7,
            'description' => null,
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Bond Paper',
            'description' => null,
            'default_unit' => 'PCS',
            'status' => 'active',
        ]);

        $futureYear = (int) now()->format('Y') + 1; // 2027

        $futurePpmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'fiscal_year' => $futureYear,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'DRAFT',
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 50000,
        ]);

        PpmpItem::query()->create([
            'ppmp_id' => $futurePpmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 10,
            'unit_cost' => 100,
            'estimated_budget' => 1000,
            'procurement_schedule' => 'Q1',
            'remarks' => 'Future plan item',
        ]);

        // 1. Gate check: office.ppmp.submit must be false for future fiscal year
        $this->assertFalse($officeUser->can('office.ppmp.submit', $futurePpmp));

        // 2. HTTP POST submit route:
        //    The can:office.ppmp.submit,ppmp middleware runs before the FormRequest
        //    and aborts with 403 when the gate denies future-year submission.
        $response = $this->actingAs($officeUser)->post(route('office.ppmp.submit', $futurePpmp->ppmp_id), [
            'remarks' => 'Trying to submit future plan',
        ]);
        $response->assertForbidden();

        // PPMP status must remain DRAFT (not submitted)
        $this->assertSame('DRAFT', $futurePpmp->fresh()->status);

        // 3. Direct service submit must throw ValidationException
        $this->expectException(ValidationException::class);
        $service = app(PpmpService::class);
        $service->submit($futurePpmp, $officeUser);
    }

    public function test_future_ppmp_cannot_be_approved_by_executive(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $executive = $this->createUser(UserRole::EXECUTIVE_OFFICER, $office);

        $futureYear = (int) now()->format('Y') + 1;

        // Simulate a future-year PPMP that somehow reached SUBMITTED status
        $futurePpmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'fiscal_year' => $futureYear,
            'fund_source' => 'GAA_CURRENT',
            'status' => 'SUBMITTED',
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 50000,
        ]);

        // The PpmpApprovalService blocks approval for future fiscal years
        $approvalService = app(\App\Services\Executive\PpmpApprovalService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Projected PPMP');
        $approvalService->updateStatus($futurePpmp, $executive, 'APPROVED');
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Test Office FY',
            'code' => 'TEST_FY',
            'description' => 'Office for fiscal year tests',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for fiscal year tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'emp_id' => sprintf('FY-%d-%s', $office->id, strtoupper(substr((string) str()->uuid(), 0, 8))),
        ]);
    }
}
