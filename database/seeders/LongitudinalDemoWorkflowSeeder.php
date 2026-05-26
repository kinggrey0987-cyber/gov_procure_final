<?php

namespace Database\Seeders;

use App\Enums\FundSource;
use App\Models\Alert;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AlertService;
use App\Services\Analytics\AnomalyDetectionService;
use App\Services\Analytics\AnomalyService;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\DeliveryVerificationService;
use App\Services\Executive\PpmpApprovalService;
use App\Services\Inventory\ClaimService;
use App\Services\Inventory\DistributionRecordService;
use App\Services\Inventory\InventoryService;
use App\Services\Office\SupplyRequestService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LongitudinalDemoWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->outputInfo('LongitudinalDemoWorkflowSeeder started.');
        $beforeCounts = $this->snapshotCounts();

        try {
            $this->outputLine('Stage: actor lookup and baseline context started.');
            $items = Item::query()->pluck('item_id', 'item_name');

            $execUser = $this->requireUserByRole('EXEC_OFFICER');
            $gsoUser = $this->requireUserByRole('GSO_OFFICER');
            $procUser = $this->requireUserByRole('PROC_OFFICER');
            [$mpdoUser, $mswdoUser, $engUser] = $this->requireOfficeUsers(3);

            $mpdoOfficeId = (int) $mpdoUser->office_id;
            $mswdoOfficeId = (int) $mswdoUser->office_id;
            $engOfficeId = (int) $engUser->office_id;

            $supplier = Supplier::query()->updateOrCreate(
                ['supplier_name' => 'Metro Office Depot Trading'],
                [
                    'contact_person' => 'Mina Solis',
                    'email' => 'orders@metroofficedepot.example',
                    'phone' => '09171234567',
                    'address' => 'Poblacion District, City Proper',
                    'status' => 'ACTIVE',
                ],
            );

            $secondarySupplier = Supplier::query()->updateOrCreate(
                ['supplier_name' => 'Northbay General Merchandising'],
                [
                    'contact_person' => 'Arvin Reyes',
                    'email' => 'sales@northbaymerch.example',
                    'phone' => '09179876543',
                    'address' => 'Industrial Road, City Proper',
                    'status' => 'ACTIVE',
                ],
            );
            $this->outputLine(sprintf(
                'Stage: actor lookup complete. Reused users/offices/items: EXEC=%s, GSO=%s, PROC=%s, OFFICE_USERS=%s; item_count=%d.',
                (string) $execUser->email,
                (string) $gsoUser->email,
                (string) $procUser->email,
                collect([$mpdoUser, $mswdoUser, $engUser])->map(fn (User $user): string => sprintf('%s@office:%d', (string) $user->email, (int) $user->office_id))->implode(', '),
                $items->count(),
            ));

            $this->outputLine('Stage: inventory baseline seeding started.');
            $this->seedInventory($items);
            $this->outputLine('Stage: inventory baseline seeded.');

            $this->outputLine('Stage: PPMP planning and approval scenarios started.');
            $approvedPpmps = $this->seedAt('2025-12-18', function () use ($execUser, $mpdoUser, $mswdoUser, $engUser, $mpdoOfficeId, $mswdoOfficeId, $engOfficeId): array {
                $this->seedPpmp(
                    officeId: $mswdoOfficeId,
                    preparer: $mswdoUser,
                    approver: $execUser,
                    fiscalYear: 2025,
                    plannedItems: [
                        ['item_name' => 'Rubbing Alcohol', 'quantity' => 30, 'unit_cost' => 24],
                        ['item_name' => 'Detergent Powder', 'quantity' => 25, 'unit_cost' => 36],
                    ],
                    desiredStatus: 'RETURNED_FOR_REVISION',
                    remarks: 'Returned PPMP example retained for executive planning review demonstration.',
                );

                $this->seedPpmp(
                    officeId: $engOfficeId,
                    preparer: $engUser,
                    approver: $execUser,
                    fiscalYear: 2027,
                    plannedItems: [
                        ['item_name' => 'Logbook', 'quantity' => 24, 'unit_cost' => 120],
                        ['item_name' => 'Mouse', 'quantity' => 10, 'unit_cost' => 330],
                    ],
                    desiredStatus: 'SUBMITTED',
                    remarks: 'Submitted PPMP example retained for pending executive approval workflow demonstration.',
                );

                return [
                'OFFICE_A' => $this->seedPpmp(
                        officeId: $mpdoOfficeId,
                        preparer: $mpdoUser,
                        approver: $execUser,
                        fiscalYear: 2026,
                        plannedItems: [
                            ['item_name' => 'Bond Paper', 'quantity' => 120, 'unit_cost' => 18],
                            ['item_name' => 'Ballpen', 'quantity' => 180, 'unit_cost' => 6],
                            ['item_name' => 'Folder', 'quantity' => 60, 'unit_cost' => 12],
                        ],
                        desiredStatus: 'APPROVED',
                        remarks: 'Late-2025 planning for Q1 office stationery and filing support.',
                    ),
                    'OFFICE_B' => $this->seedPpmp(
                        officeId: $mswdoOfficeId,
                        preparer: $mswdoUser,
                        approver: $execUser,
                        fiscalYear: 2026,
                        plannedItems: [
                            ['item_name' => 'Rubbing Alcohol', 'quantity' => 80, 'unit_cost' => 25],
                            ['item_name' => 'Detergent Powder', 'quantity' => 70, 'unit_cost' => 38],
                            ['item_name' => 'Safety Gloves', 'quantity' => 40, 'unit_cost' => 45],
                        ],
                        desiredStatus: 'APPROVED',
                        remarks: 'MSWDO revised PPMP approved for FY 2026 execution scenarios.',
                    ),
                    'OFFICE_C' => $this->seedPpmp(
                        officeId: $engOfficeId,
                        preparer: $engUser,
                        approver: $execUser,
                        fiscalYear: 2026,
                        plannedItems: [
                            ['item_name' => 'Printer Ink', 'quantity' => 24, 'unit_cost' => 850],
                            ['item_name' => 'Mouse', 'quantity' => 30, 'unit_cost' => 325],
                            ['item_name' => 'Logbook', 'quantity' => 20, 'unit_cost' => 120],
                        ],
                        desiredStatus: 'APPROVED',
                        remarks: 'Engineering operating stock plan for first half of 2026.',
                    ),
                ];
            });
            $this->outputLine('Stage: PPMP planning and approval scenarios seeded.');

            $requests = [];
            $purchaseOrders = [];
            $this->outputLine('Stage: January office requests started.');

            $this->seedAt('2026-01-08', function () use (&$requests, $mpdoUser, $mswdoUser, $engUser, $approvedPpmps): void {
                    $requests['mpdo-jan-paper'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202601-01',
                        purpose: 'January planning office stationery replenishment.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-01-18',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 24],
                        ],
                    );

                    $requests['mpdo-jan-file'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202601-02',
                        purpose: 'January filing and meeting pack support.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-01-22',
                        lines: [
                            ['item_name' => 'Folder', 'quantity' => 18],
                        ],
                    );

                    $requests['mswdo-jan-hygiene'] = $this->seedSupplyRequest(
                        actor: $mswdoUser,
                        ppmp: $approvedPpmps['OFFICE_B'],
                        requestCode: 'SR-MSWDO-202601-01',
                        purpose: 'January sanitation and desk replenishment.',
                        requestCategory: 'Cleaning Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-01-20',
                        lines: [
                            ['item_name' => 'Rubbing Alcohol', 'quantity' => 10],
                        ],
                    );

                    $requests['eng-jan-ink'] = $this->seedSupplyRequest(
                        actor: $engUser,
                        ppmp: $approvedPpmps['OFFICE_C'],
                        requestCode: 'SR-ENG-202601-01',
                        purpose: 'January engineering office print queue support.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-01-21',
                        lines: [
                            ['item_name' => 'Printer Ink', 'quantity' => 4],
                        ],
                    );
            });
            $this->outputLine('Stage: January office requests seeded.');

            $this->outputLine('Stage: February procurement scenarios started.');
            $this->seedAt('2026-02-10', function () use (&$requests, &$purchaseOrders, $gsoUser, $procUser, $supplier, $secondarySupplier): void {
                    $purchaseOrders['mpdo-jan-paper'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-jan-paper'],
                        supplier: $supplier,
                        unitPrice: 18,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-02-18',
                        deliveryNotes: 'January stationery batch queued for February release.',
                    );

                    $purchaseOrders['mpdo-jan-file'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-jan-file'],
                        supplier: $supplier,
                        unitPrice: 12,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-02-19',
                        deliveryNotes: 'Filing supplies for the planning office.',
                    );

                    $purchaseOrders['mswdo-jan-hygiene'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mswdo-jan-hygiene'],
                        supplier: $secondarySupplier,
                        unitPrice: 26,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-02-20',
                        deliveryNotes: 'Welfare hygiene replenishment for service desk use.',
                    );

                    $purchaseOrders['eng-jan-ink'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['eng-jan-ink'],
                        supplier: $supplier,
                        unitPrice: 860,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-02-21',
                        deliveryNotes: 'Printer consumables for engineering plotting and records work.',
                    );
            });
            $this->outputLine('Stage: February procurement scenarios seeded.');

            $this->outputLine('Stage: February office requests started.');
            $this->seedAt('2026-02-18', function () use (&$requests, $mpdoUser, $mswdoUser, $engUser, $approvedPpmps): void {
                    $requests['mpdo-feb-paper'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202602-01',
                        purpose: 'February planning office paper replenishment.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-02-26',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 32],
                        ],
                    );

                    $requests['mswdo-feb-cleaning'] = $this->seedSupplyRequest(
                        actor: $mswdoUser,
                        ppmp: $approvedPpmps['OFFICE_B'],
                        requestCode: 'SR-MSWDO-202602-01',
                        purpose: 'February hygiene support for desk and outreach use.',
                        requestCategory: 'Cleaning Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-02-24',
                        lines: [
                            ['item_name' => 'Detergent Powder', 'quantity' => 16],
                        ],
                    );

                    $requests['eng-feb-mouse'] = $this->seedSupplyRequest(
                        actor: $engUser,
                        ppmp: $approvedPpmps['OFFICE_C'],
                        requestCode: 'SR-ENG-202602-01',
                        purpose: 'February workstation replacement mouse request.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-02-25',
                        lines: [
                            ['item_name' => 'Mouse', 'quantity' => 6],
                        ],
                    );
            });
            $this->outputLine('Stage: February office requests seeded.');

            $this->outputLine('Stage: March procurement scenarios started.');
            $this->seedAt('2026-03-12', function () use (&$requests, &$purchaseOrders, $gsoUser, $procUser, $supplier, $secondarySupplier): void {
                    $purchaseOrders['mpdo-feb-paper'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-feb-paper'],
                        supplier: $supplier,
                        unitPrice: 19,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-03-18',
                        deliveryNotes: 'February planning office replenishment.',
                    );

                    $purchaseOrders['mswdo-feb-cleaning'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mswdo-feb-cleaning'],
                        supplier: $secondarySupplier,
                        unitPrice: 27,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-03-19',
                        deliveryNotes: 'March hygiene replenishment for social welfare outreach.',
                    );

                    $purchaseOrders['eng-feb-mouse'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['eng-feb-mouse'],
                        supplier: $supplier,
                        unitPrice: 340,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-03-20',
                        deliveryNotes: 'Engineering workstation replacement batch.',
                    );
            });
            $this->outputLine('Stage: March procurement scenarios seeded.');

            $this->outputLine('Stage: March office requests started.');
            $this->seedAt('2026-03-18', function () use (&$requests, $mpdoUser, $mswdoUser, $engUser, $approvedPpmps): void {
                    $requests['mpdo-mar-forms'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202603-01',
                        purpose: 'March routing forms and filing replenishment.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-03-27',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 22],
                            ['item_name' => 'Folder', 'quantity' => 10],
                        ],
                    );

                    $requests['mswdo-mar-cleaning'] = $this->seedSupplyRequest(
                        actor: $mswdoUser,
                        ppmp: $approvedPpmps['OFFICE_B'],
                        requestCode: 'SR-MSWDO-202603-01',
                        purpose: 'March hygiene replenishment for outreach desk use.',
                        requestCategory: 'Cleaning Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-03-26',
                        lines: [
                            ['item_name' => 'Rubbing Alcohol', 'quantity' => 12],
                            ['item_name' => 'Detergent Powder', 'quantity' => 10],
                        ],
                    );

                    $requests['eng-mar-print'] = $this->seedSupplyRequest(
                        actor: $engUser,
                        ppmp: $approvedPpmps['OFFICE_C'],
                        requestCode: 'SR-ENG-202603-01',
                        purpose: 'March engineering print queue replenishment.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-03-28',
                        lines: [
                            ['item_name' => 'Printer Ink', 'quantity' => 5],
                        ],
                    );
            });
            $this->outputLine('Stage: March office requests seeded.');

            $this->seedAt('2026-03-22', function () use (&$requests, &$purchaseOrders, $gsoUser, $procUser, $supplier, $secondarySupplier): void {
                    $purchaseOrders['mpdo-mar-forms'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-mar-forms'],
                        supplier: $supplier,
                        unitPrice: 19,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-03-30',
                        deliveryNotes: 'March planning office replenishment.',
                    );

                    $purchaseOrders['mswdo-mar-cleaning'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mswdo-mar-cleaning'],
                        supplier: $secondarySupplier,
                        unitPrice: 29,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-03-31',
                        deliveryNotes: 'March hygiene replenishment for social welfare outreach.',
                    );

                    $purchaseOrders['eng-mar-print'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['eng-mar-print'],
                        supplier: $supplier,
                        unitPrice: 865,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-03-31',
                        deliveryNotes: 'March engineering print consumables batch.',
                    );
            });

            $this->outputLine('Stage: delivery and distribution scenarios started.');
            $marchDelivery = $this->seedAt('2026-03-28', function () use ($gsoUser, $mpdoUser, $purchaseOrders, $supplier): Delivery {
                return $this->seedDeliveryAndDistributionFlow(
                    gsoUser: $gsoUser,
                    officeUser: $mpdoUser,
                    purchaseOrder: $purchaseOrders['mpdo-mar-forms'],
                    supplier: $purchaseOrders['mpdo-mar-forms']->supplier ?? $supplier,
                );
            });

            $this->seedAt('2026-03-30', function () use ($gsoUser, $mswdoUser, $purchaseOrders, $secondarySupplier): void {
                $this->seedDeliveryAndDistributionFlow(
                    gsoUser: $gsoUser,
                    officeUser: $mswdoUser,
                    purchaseOrder: $purchaseOrders['mswdo-mar-cleaning'],
                    supplier: $purchaseOrders['mswdo-mar-cleaning']->supplier ?? $secondarySupplier,
                );
            });

            $this->seedAt('2026-03-31', function () use ($gsoUser, $engUser, $purchaseOrders, $supplier): void {
                $this->seedDeliveryAndDistributionFlow(
                    gsoUser: $gsoUser,
                    officeUser: $engUser,
                    purchaseOrder: $purchaseOrders['eng-mar-print'],
                    supplier: $purchaseOrders['eng-mar-print']->supplier ?? $supplier,
                );
            });

            $this->seedAt('2026-04-10', function () use (&$requests, &$purchaseOrders, $gsoUser, $mpdoUser, $engUser, $approvedPpmps, $procUser, $supplier, $secondarySupplier): void {
                    $requests['mpdo-apr-paper'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202604-01',
                        purpose: 'April paper surge for report printing and archive work.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-04-18',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 70],
                        ],
                    );

                    $requests['mpdo-apr-surge'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202604-02',
                        purpose: 'April supplemental stationery request after meeting season.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-04-19',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 18],
                        ],
                    );

                    $requests['eng-apr-ink'] = $this->seedSupplyRequest(
                        actor: $engUser,
                        ppmp: $approvedPpmps['OFFICE_C'],
                        requestCode: 'SR-ENG-202604-01',
                        purpose: 'April engineering ink replenishment for drawing production.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-04-18',
                        lines: [
                            ['item_name' => 'Printer Ink', 'quantity' => 8],
                        ],
                    );

                    $purchaseOrders['mpdo-apr-paper'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-apr-paper'],
                        supplier: $supplier,
                        unitPrice: 74,
                        procurementMethod: 'NEGOTIATED',
                        expectedDeliveryDate: '2026-04-22',
                        deliveryNotes: 'April paper surge with supplier rate pressure.',
                    );

                    $purchaseOrders['mpdo-apr-surge'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-apr-surge'],
                        supplier: $supplier,
                        unitPrice: 22,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-04-23',
                        deliveryNotes: 'Supplemental office supply batch after month-end review.',
                    );

                    $purchaseOrders['eng-apr-ink'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['eng-apr-ink'],
                        supplier: $secondarySupplier,
                        unitPrice: 875,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-04-24',
                        deliveryNotes: 'April engineering ink replenishment.',
                    );
            });

            $this->seedAt('2026-04-21', function () use (&$requests, $mpdoUser, $mswdoUser, $engUser, $approvedPpmps): void {
                    $requests['mpdo-apr-burst-1'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202604-03',
                        purpose: 'April routing and filing burst after committee consultations.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-04-28',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 24],
                        ],
                    );

                    $requests['mpdo-apr-burst-2'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202604-04',
                        purpose: 'April follow-up paper batch for planning outputs.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-04-29',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 16],
                        ],
                    );

                    $requests['mpdo-apr-burst-3'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202604-05',
                        purpose: 'April exceptional paper surge tied to quarterly report printing.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-04-29',
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => 140],
                        ],
                    );

                    $requests['mpdo-apr-ready'] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $approvedPpmps['OFFICE_A'],
                        requestCode: 'SR-MPDO-202604-06',
                        purpose: 'April stock-ready ballpen requisition for immediate release.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-04-26',
                        lines: [
                            ['item_name' => 'Ballpen', 'quantity' => 12],
                        ],
                    );

                    $requests['mswdo-apr-hygiene'] = $this->seedSupplyRequest(
                        actor: $mswdoUser,
                        ppmp: $approvedPpmps['OFFICE_B'],
                        requestCode: 'SR-MSWDO-202604-01',
                        purpose: 'April hygiene replenishment for outreach and desk work.',
                        requestCategory: 'Cleaning Supplies',
                        priorityLevel: 'HIGH',
                        neededByDate: '2026-04-27',
                        lines: [
                            ['item_name' => 'Detergent Powder', 'quantity' => 20],
                        ],
                    );

                    $requests['eng-apr-ink-burst'] = $this->seedSupplyRequest(
                        actor: $engUser,
                        ppmp: $approvedPpmps['OFFICE_C'],
                        requestCode: 'SR-ENG-202604-02',
                        purpose: 'April engineering print burst for drawings and logs.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: 'NORMAL',
                        neededByDate: '2026-04-27',
                        lines: [
                            ['item_name' => 'Printer Ink', 'quantity' => 6],
                        ],
                    );
            });

            $this->seedAt('2026-04-23', function () use (&$purchaseOrders, $gsoUser, $procUser, $supplier, $secondarySupplier, $requests): void {
                    $purchaseOrders['mpdo-apr-burst-1'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-apr-burst-1'],
                        supplier: $supplier,
                        unitPrice: 20,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-04-30',
                        deliveryNotes: 'April paper release batch one.',
                    );

                    $purchaseOrders['mpdo-apr-burst-2'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-apr-burst-2'],
                        supplier: $supplier,
                        unitPrice: 21,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-04-30',
                        deliveryNotes: 'April paper release batch two.',
                    );

                    $purchaseOrders['mpdo-apr-burst-3'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mpdo-apr-burst-3'],
                        supplier: $secondarySupplier,
                        unitPrice: 82,
                        procurementMethod: 'NEGOTIATED',
                        expectedDeliveryDate: '2026-05-05',
                        deliveryNotes: 'Negotiated emergency paper purchase for quarterly output.',
                    );

                    $purchaseOrders['mswdo-apr-hygiene'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['mswdo-apr-hygiene'],
                        supplier: $secondarySupplier,
                        unitPrice: 31,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-04-30',
                        deliveryNotes: 'April hygiene replenishment for social welfare outreach.',
                    );

                    $purchaseOrders['eng-apr-ink-burst'] = $this->seedProcurementFlow(
                        gsoActor: $gsoUser,
                        actor: $procUser,
                        supplyRequest: $requests['eng-apr-ink-burst'],
                        supplier: $supplier,
                        unitPrice: 890,
                        procurementMethod: 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: '2026-04-30',
                        deliveryNotes: 'April engineering print burst for drawings and logs.',
                    );
            });

            $this->seedAt('2026-04-24', function () use ($gsoUser, $requests): void {
                $this->seedStockReadyRelease(
                    gsoUser: $gsoUser,
                    supplyRequest: $requests['mpdo-apr-ready'],
                    releaseNotes: 'Stock-ready request released directly from inventory without procurement cycle.',
                );
            });

            $aprilDelivery = $this->seedAt('2026-04-25', function () use ($gsoUser, $engUser, $purchaseOrders, $supplier): Delivery {
                return $this->seedDeliveryAndDistributionFlow(
                    gsoUser: $gsoUser,
                    officeUser: $engUser,
                    purchaseOrder: $purchaseOrders['eng-apr-ink'],
                    supplier: $purchaseOrders['eng-apr-ink']->supplier ?? $supplier,
                );
            });

            $burstDelivery = $this->seedAt('2026-04-29', function () use ($gsoUser, $mpdoUser, $purchaseOrders, $supplier): Delivery {
                return $this->seedDeliveryAndDistributionFlow(
                    gsoUser: $gsoUser,
                    officeUser: $mpdoUser,
                    purchaseOrder: $purchaseOrders['mpdo-apr-burst-3'],
                    supplier: $purchaseOrders['mpdo-apr-burst-3']->supplier ?? $supplier,
                    withDiscrepancy: true,
                );
            });

            $this->seedAt('2026-04-30', function () use ($gsoUser, $mpdoUser, $purchaseOrders, $supplier): void {
                $this->seedDeliveryAndDistributionFlow(
                    gsoUser: $gsoUser,
                    officeUser: $mpdoUser,
                    purchaseOrder: $purchaseOrders['mpdo-apr-burst-1'],
                    supplier: $purchaseOrders['mpdo-apr-burst-1']->supplier ?? $supplier,
                );
            });

            $this->outputLine('Stage: delivery and distribution scenarios seeded.');

            $this->outputLine('Stage: selective claims started.');
            $this->seedClaimForDelivery(
                delivery: $marchDelivery,
                officeUser: $mpdoUser,
                gsoUser: $gsoUser,
                claimType: 'SHORTAGE',
                claimLocation: 'Planning office receiving desk',
                createRemarks: 'Selective claim: March distribution discrepancy reported by MPDO.',
                resolveRemarks: 'Resolved after office count confirmation in post-release review.',
            );

            $this->seedClaimForDelivery(
                delivery: $aprilDelivery,
                officeUser: $engUser,
                gsoUser: $gsoUser,
                claimType: 'QUANTITY_MISMATCH',
                claimLocation: 'Engineering receiving station',
                createRemarks: 'Selective claim: April engineering release mismatch for traceability.',
                resolveRemarks: 'Resolved after engineering confirmed final released count.',
            );

            $this->seedClaimForDelivery(
                delivery: $burstDelivery,
                officeUser: $mpdoUser,
                gsoUser: $gsoUser,
                claimType: 'SHORTAGE',
                claimLocation: 'MPDO stock room',
                createRemarks: 'Selective claim: April paper surge release shortage review.',
                resolveRemarks: null,
            );
            $this->outputLine('Stage: selective claims seeded.');

            $this->seedAlerts($gsoUser, $purchaseOrders['mpdo-apr-paper'], $aprilDelivery);
            $this->seedAlerts($gsoUser, $purchaseOrders['mpdo-apr-burst-3'], $burstDelivery);

            $this->outputLine('Stage: disclosure linkage confirmation: ExecutiveDisclosureDemoSeeder runs next in DatabaseSeeder chain.');

            $this->outputLine('Stage: normalization started.');
            $this->seedInventoryShowcases();
            $this->normalizeGsoLinkedDemoData($gsoUser);
            $this->outputLine('Stage: normalization finished.');

            $this->outputLine('Stage: executive detections started.');
            app(AnomalyDetectionService::class)->runExecutiveDetections($execUser);
            $this->outputLine('Stage: executive detections finished.');

            $this->outputLine('Stage: duplicate anomaly collapse started.');
            $dedupedAnomalies = app(AnomalyService::class)->collapseActiveDuplicates();
            $this->outputLine(sprintf('Anomaly duplicate cleanup dismissed %d duplicate active record(s).', $dedupedAnomalies));
            $this->outputLine('Stage: duplicate anomaly collapse finished.');

            $afterCounts = $this->snapshotCounts();
            $this->reportCounterDelta($beforeCounts, $afterCounts);

            $this->outputLine(sprintf(
                'Longitudinal anchors seeded around %s through %s.',
                Carbon::parse('2025-12-18')->toDateString(),
                Carbon::parse('2026-04-25')->toDateString(),
            ));
        } catch (Throwable $exception) {
            Log::error('LongitudinalDemoWorkflowSeeder failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->command?->error('LongitudinalDemoWorkflowSeeder failed: '.$exception->getMessage());
            throw $exception;
        }

        $this->outputInfo('LongitudinalDemoWorkflowSeeder completed.');
    }

    /**
     * @return array<string, int>
     */
    private function snapshotCounts(): array
    {
        return [
            'ppmp' => (int) DB::table('ppmp')->count(),
            'ppmp_items' => (int) DB::table('ppmp_items')->count(),
            'supply_requests' => (int) DB::table('supply_requests')->count(),
            'supply_request_items' => (int) DB::table('supply_request_items')->count(),
            'purchase_requests' => (int) DB::table('purchase_requests')->count(),
            'purchase_request_items' => (int) DB::table('purchase_request_items')->count(),
            'inventory' => (int) DB::table('inventory')->count(),
            'deliveries' => (int) DB::table('deliveries')->count(),
            'distribution_records' => (int) DB::table('distribution_records')->count(),
            'anomalies' => (int) DB::table('anomalies')->count(),
            'alerts' => (int) DB::table('alerts')->count(),
        ];
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     */
    private function reportCounterDelta(array $before, array $after): void
    {
        $this->outputInfo('LongitudinalDemoWorkflowSeeder insert deltas:');

        foreach ($after as $table => $afterCount) {
            $beforeCount = $before[$table] ?? 0;
            $delta = $afterCount - $beforeCount;
            $this->outputLine(sprintf('- %s: +%d (before=%d, after=%d)', $table, $delta, $beforeCount, $afterCount));
        }
    }

    private function seedAt(string $timestamp, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse($timestamp)->startOfDay());

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    private function outputInfo(string $message): void
    {
        $this->command?->info($message);
        Log::info($message);
    }

    private function outputLine(string $message): void
    {
        $this->command?->line($message);
        Log::info($message);
    }

    private function requireUserByRole(string $roleCode): User
    {
        $user = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', $roleCode))
            ->whereNotNull('office_id')
            ->with(['role', 'office'])
            ->orderBy('id')
            ->first();

        if (! $user instanceof User) {
            throw new RuntimeException(sprintf('Required existing user with role %s was not found. No random users were created.', $roleCode));
        }

        return $user;
    }

    /**
     * @return array<int, User>
     */
    private function requireOfficeUsers(int $minimum): array
    {
        $users = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', 'OFFICE_USER'))
            ->whereNotNull('office_id')
            ->with(['role', 'office'])
            ->orderBy('office_id')
            ->orderBy('id')
            ->get()
            ->unique(fn (User $user): int => (int) $user->office_id)
            ->values();

        if ($users->count() < $minimum) {
            throw new RuntimeException(sprintf(
                'Required at least %d existing OFFICE_USER users assigned to distinct offices; found %d. No random users were created.',
                $minimum,
                $users->count(),
            ));
        }

        return $users->take($minimum)->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $itemsByName
     */
    private function requireItemId($itemsByName, string $itemName): int
    {
        $itemId = (int) ($itemsByName->get($itemName) ?? 0);

        if ($itemId <= 0) {
            throw new RuntimeException(sprintf('Required item not found: %s', $itemName));
        }

        return $itemId;
    }

    /**
     * @param  array<int, array{item_name: string, quantity: int|float, unit_cost: int|float}>  $plannedItems
     */
    private function seedPpmp(int $officeId, User $preparer, User $approver, int $fiscalYear, array $plannedItems, string $desiredStatus, string $remarks): Ppmp
    {
        $ppmp = Ppmp::query()->updateOrCreate(
            [
                'office_id' => $officeId,
                'fiscal_year' => $fiscalYear,
            ],
                [
                    'prepared_by' => (int) $preparer->id,
                    'fund_source' => FundSource::GAA_CURRENT->value,
                    'status' => 'SUBMITTED',
                    'total_estimated_budget' => 0,
                    'date_prepared' => now()->toDateString(),
                'date_approved' => null,
                'remarks' => $remarks,
            ],
        );

        $totalEstimatedBudget = 0.0;
        foreach ($plannedItems as $plannedItem) {
            $itemId = (int) Item::query()->where('item_name', $plannedItem['item_name'])->value('item_id');
            if ($itemId <= 0) {
                throw new RuntimeException(sprintf('Missing item for PPMP seed row: %s', $plannedItem['item_name']));
            }

            $unitCost = (float) $plannedItem['unit_cost'];
            $quantity = (float) $plannedItem['quantity'];
            $estimatedBudget = round($unitCost * $quantity, 2);
            $totalEstimatedBudget += $estimatedBudget;

            PpmpItem::query()->updateOrCreate(
                [
                    'ppmp_id' => (int) $ppmp->ppmp_id,
                    'item_id' => $itemId,
                ],
                [
                    'item_category_id' => (int) Item::query()->where('item_id', $itemId)->value('item_category_id'),
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'estimated_budget' => $estimatedBudget,
                    'procurement_schedule' => now()->addWeeks(2)->toDateString(),
                    'remarks' => (string) ($plannedItem['remarks'] ?? sprintf('Required specification for %s from seeded PPMP item remarks.', $plannedItem['item_name'])),
                ],
            );
        }

        $ppmp->forceFill([
            'total_estimated_budget' => $totalEstimatedBudget,
        ])->save();

        if ((string) $ppmp->status === 'SUBMITTED') {
            $service = app(PpmpApprovalService::class);
            $ppmp = match ($desiredStatus) {
                'APPROVED' => $service->updateStatus($ppmp, $approver, 'APPROVED', 'Approved for longitudinal demo workflow seeding.'),
                'RETURNED_FOR_REVISION' => $service->updateStatus($ppmp, $approver, 'RETURNED_FOR_REVISION', 'Returned for revision for longitudinal demo workflow seeding.'),
                'REJECTED' => $service->updateStatus($ppmp, $approver, 'REJECTED', 'Rejected for longitudinal demo workflow seeding.'),
                default => $ppmp,
            };
        }

        return $ppmp->fresh(['office', 'preparer', 'approver', 'items.item', 'items.itemCategory']) ?? $ppmp;
    }

    /**
     * @param  array<int, array{item_name: string, quantity: int|float}>  $lines
     */
    private function seedSupplyRequest(
        User $actor,
        Ppmp $ppmp,
        string $requestCode,
        string $purpose,
        string $requestCategory,
        string $priorityLevel,
        string $neededByDate,
        array $lines,
    ): SupplyRequest {
        if ((string) $ppmp->status !== 'APPROVED') {
            throw new RuntimeException(sprintf(
                'Supply request %s cannot be seeded from non-approved PPMP %d (%s).',
                $requestCode,
                (int) $ppmp->ppmp_id,
                (string) $ppmp->status,
            ));
        }

        $supplyRequest = SupplyRequest::query()->updateOrCreate(
            ['request_code' => $requestCode],
            [
                'office_id' => (int) $actor->office_id,
                'requested_by' => (int) $actor->id,
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => $neededByDate,
                'request_category' => $requestCategory,
                'purpose' => $purpose,
                'priority_level' => $priorityLevel,
                'status' => 'DRAFT',
                'review_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ],
        );

        if ((string) $supplyRequest->status !== 'DRAFT') {
            return $supplyRequest->fresh(['items', 'office', 'requester', 'approver', 'ppmp']) ?? $supplyRequest;
        }

        foreach ($lines as $line) {
            $item = Item::query()->where('item_name', $line['item_name'])->first();
            if (! $item instanceof Item) {
                throw new RuntimeException(sprintf('Missing item while seeding supply request line: %s', $line['item_name']));
            }

            $ppmpItem = PpmpItem::query()
                ->where('ppmp_id', (int) $ppmp->ppmp_id)
                ->where('item_id', (int) $item->item_id)
                ->first();

            if (! $ppmpItem instanceof PpmpItem) {
                throw new RuntimeException(sprintf(
                    'Missing PPMP item mapping for item_id %d in ppmp_id %d while seeding supply request.',
                    (int) $item->item_id,
                    (int) $ppmp->ppmp_id,
                ));
            }

            SupplyRequestItem::query()->updateOrCreate(
                [
                    'supply_request_id' => (int) $supplyRequest->supply_request_id,
                    'ppmp_item_id' => (int) $ppmpItem->ppmp_item_id,
                ],
                [
                    'item_id' => (int) $item->item_id,
                    'item_name_snapshot' => (string) $item->item_name,
                    'quantity_requested' => (float) $line['quantity'],
                ],
            );
        }

        $service = app(SupplyRequestService::class);
        $submitted = $service->submit($supplyRequest->fresh(['items']) ?? $supplyRequest, $actor, 'Seeded office request submitted for longitudinal demo workflow.');

        return $submitted->fresh(['items', 'office', 'requester', 'approver', 'ppmp']) ?? $submitted;
    }

    private function seedProcurementFlow(
        User $gsoActor,
        User $actor,
        SupplyRequest $supplyRequest,
        Supplier $supplier,
        float $unitPrice,
        string $procurementMethod,
        string $expectedDeliveryDate,
        string $deliveryNotes,
    ): PurchaseOrder {
        $purchaseRequestService = app(PurchaseRequestService::class);
        $purchaseOrderService = app(PurchaseOrderService::class);

        $purchaseRequest = PurchaseRequest::query()->where('supply_request_id', (int) $supplyRequest->supply_request_id)->first();
        if (! $purchaseRequest instanceof PurchaseRequest) {
            $this->forceShortageForSupplyRequest($supplyRequest);

            $purchaseRequest = $purchaseRequestService->queueFromSupplyRequest($gsoActor, [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'remarks' => 'Queued by GSO during longitudinal demo shortage validation.',
            ]);
        }

        if ((string) $purchaseRequest->status === 'OPEN') {
            $purchaseRequest = $purchaseRequestService->process($purchaseRequest, $actor, 'Opened for procurement processing.');
        }

        $purchaseOrder = PurchaseOrder::query()->where('purchase_request_id', (int) $purchaseRequest->purchase_request_id)->first();
        if (! $purchaseOrder instanceof PurchaseOrder) {
            $purchaseOrder = $purchaseOrderService->create($actor, [
                'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                'supplier_id' => (int) $supplier->supplier_id,
                'procurement_method' => $procurementMethod,
                'expected_delivery_date' => $expectedDeliveryDate,
                'terms_notes' => 'Standard demo procurement terms.',
                'remarks' => $deliveryNotes,
            ]);
        }

        $purchaseOrder->loadMissing('items', 'purchaseRequest', 'supplyRequest');

        if (! $purchaseOrder->items()->exists()) {
            foreach ($supplyRequest->items as $requestItem) {
                if (! $requestItem instanceof SupplyRequestItem) {
                    continue;
                }

                $purchaseOrderService->addItem($purchaseOrder, $actor, [
                    'supply_request_item_id' => (int) $requestItem->supply_request_item_id,
                    'item_id' => (int) $requestItem->item_id,
                    'item_name_snapshot' => (string) $requestItem->item_name_snapshot,
                    'specification' => 'Demo procurement specification.',
                    'quantity_ordered' => (float) $requestItem->quantity_requested,
                    'unit' => 'piece',
                    'unit_price' => $unitPrice,
                ]);
            }
        }

        if ((string) $purchaseOrder->status === 'DRAFT') {
            $purchaseOrder = $purchaseOrderService->place($purchaseOrder, $actor, 'Purchase order placed for longitudinal demo workflow.');
        }

        return $purchaseOrder->fresh(['items', 'supplier', 'creator', 'purchaseRequest', 'supplyRequest']) ?? $purchaseOrder;
    }

    private function seedDeliveryAndDistributionFlow(
        User $gsoUser,
        User $officeUser,
        PurchaseOrder $purchaseOrder,
        Supplier $supplier,
        bool $withDiscrepancy = false,
    ): Delivery {
        $deliveryService = app(DeliveryService::class);
        $verificationService = app(DeliveryVerificationService::class);
        $inventoryService = app(InventoryService::class);
        $distributionService = app(DistributionRecordService::class);

        $delivery = Delivery::query()->where('purchase_order_id', (int) $purchaseOrder->purchase_order_id)->first();
        if (! $delivery instanceof Delivery) {
            $delivery = $deliveryService->create($gsoUser, [
                'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                'supplier_id' => (int) $supplier->supplier_id,
                'expected_arrival_date' => now()->addDays(5)->toDateString(),
                'actual_delivery_date' => now()->addDays(7)->toDateString(),
                'remarks' => 'Delivery created for longitudinal demo workflow.',
            ]);
        }

        if (! $delivery->items()->exists()) {
            foreach ($purchaseOrder->items as $purchaseOrderItem) {
                if (! $purchaseOrderItem instanceof PurchaseOrderItem) {
                    continue;
                }

                DeliveryItem::query()->create([
                    'delivery_id' => (int) $delivery->delivery_id,
                    'purchase_order_item_id' => (int) $purchaseOrderItem->purchase_order_item_id,
                    'expected_quantity' => (float) $purchaseOrderItem->quantity_ordered,
                    'received_quantity' => $withDiscrepancy
                        ? max(1, (float) $purchaseOrderItem->quantity_ordered - 2)
                        : (float) $purchaseOrderItem->quantity_ordered,
                    'damaged_quantity' => 0,
                    'missing_quantity' => $withDiscrepancy
                        ? min(2, (float) $purchaseOrderItem->quantity_ordered)
                        : 0,
                    'item_condition' => $withDiscrepancy ? 'PARTIAL' : 'GOOD',
                    'remarks' => $withDiscrepancy
                        ? 'Demo discrepancy flow: partial delivery with short quantity.'
                        : 'Demo delivery verified without discrepancy.',
                ]);
            }
        }

        if ((string) $delivery->receipt_status === 'PENDING') {
            if (! $delivery->actual_delivery_date) {
                $delivery->forceFill([
                    'actual_delivery_date' => now()->toDateString(),
                ])->save();
            }

            $delivery = $deliveryService->markReceived($delivery, $gsoUser, 'Received at the GSO receiving area.');
        }

        if ($delivery->verifications()->doesntExist()) {
            $verificationService->create($delivery, $gsoUser, [
                'verification_date' => now()->toDateString(),
                'verification_status' => $withDiscrepancy ? 'REJECTED' : 'APPROVED',
                'discrepancy_notes' => $withDiscrepancy
                    ? 'Discrepancy noted: partial quantity received compared to purchase order quantities.'
                    : null,
                'remarks' => $withDiscrepancy
                    ? 'Rejected with discrepancy notes for BAC/procurement follow-up in the demo workflow.'
                    : 'Verified and cleared for inventory sync in the demo workflow.',
            ]);
        }

        $delivery = $delivery->fresh(['items', 'purchaseOrder', 'supplier', 'receiver']) ?? $delivery;

        if ((string) $delivery->receipt_status === 'VERIFIED') {
            $inventoryService->updateFromVerifiedDelivery($delivery, $gsoUser);
        }

        if ((string) $delivery->receipt_status !== 'VERIFIED') {
            return $delivery->fresh(['purchaseOrder', 'supplier', 'receiver', 'items']) ?? $delivery;
        }

        $distributionRecord = DistributionRecord::query()->where('delivery_id', (int) $delivery->delivery_id)->first();
        if (! $distributionRecord instanceof DistributionRecord) {
            $distributionRecord = $distributionService->createRecord($gsoUser, [
                'supply_request_id' => (int) $purchaseOrder->supply_request_id,
                'delivery_id' => (int) $delivery->delivery_id,
                'office_id' => (int) $officeUser->office_id,
                'received_by' => (int) $officeUser->id,
                'release_date' => now()->toDateString(),
                'storage_location' => 'Main GSO storage bay',
                'notes' => 'Seeded distribution for office handoff.',
            ]);
        }

        if (! $distributionRecord->items()->exists()) {
            $deliveryItem = $delivery->items()->first();
            $purchaseOrderItem = $deliveryItem?->purchaseOrderItem;
            if ($deliveryItem instanceof DeliveryItem && $purchaseOrderItem instanceof PurchaseOrderItem) {
                $distributionService->addDistributionItem($distributionRecord, $gsoUser, [
                    'item_id' => (int) $purchaseOrderItem->item_id,
                    'received_by' => (int) $officeUser->id,
                    'item_name_snapshot' => (string) $purchaseOrderItem->item_name_snapshot,
                    'quantity_released' => min((float) $deliveryItem->received_quantity, (float) $purchaseOrderItem->quantity_ordered - 2),
                    'unit' => (string) $purchaseOrderItem->unit,
                    'remarks' => 'Released to the office for demo distribution.',
                ]);
            }
        }

        if ((string) $distributionRecord->status === 'RELEASED') {
            $distributionService->markReceivedByOffice($distributionRecord, $officeUser, 'Office acknowledged receipt of released supplies.');
        }

        return $delivery->fresh(['purchaseOrder', 'supplier', 'receiver', 'items']) ?? $delivery;
    }

    private function seedStockReadyRelease(User $gsoUser, SupplyRequest $supplyRequest, string $releaseNotes): void
    {
        $inventoryService = app(InventoryService::class);

        $supplyRequest->loadMissing('items');
        foreach ($supplyRequest->items as $requestItem) {
            if (! $requestItem instanceof SupplyRequestItem) {
                continue;
            }

            try {
                $inventoryService->releaseStock(
                    itemId: (int) $requestItem->item_id,
                    quantity: (float) $requestItem->quantity_requested,
                    actor: $gsoUser,
                    referenceType: SupplyRequest::class,
                    referenceId: (int) $supplyRequest->supply_request_id,
                    remarks: 'Stock-ready request released directly from inventory.',
                );
            } catch (Throwable $exception) {
                Log::warning('LongitudinalDemoWorkflowSeeder stock-ready release skipped for item.', [
                    'supply_request_id' => (int) $supplyRequest->supply_request_id,
                    'item_id' => (int) $requestItem->item_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $supplyRequest->forceFill([
            'status' => 'COMPLETED',
            'review_notes' => $releaseNotes,
            'approved_by' => (int) $gsoUser->id,
            'approved_at' => now(),
        ])->save();
    }

    private function forceShortageForSupplyRequest(SupplyRequest $supplyRequest): void
    {
        $supplyRequest->loadMissing('items');

        foreach ($supplyRequest->items as $requestItem) {
            if (! $requestItem instanceof SupplyRequestItem) {
                continue;
            }

            $requested = (float) $requestItem->quantity_requested;
            if ($requested <= 0) {
                continue;
            }

            $inventory = Inventory::query()->where('item_id', (int) $requestItem->item_id)->first();
            if (! $inventory instanceof Inventory) {
                continue;
            }

            $forcedAvailable = max(min((float) $inventory->available_stock, $requested - 2), 0);
            $forcedOnHand = max((float) $inventory->stock_on_hand, $forcedAvailable);

            $inventory->forceFill([
                'available_stock' => $forcedAvailable,
                'stock_on_hand' => $forcedOnHand,
                'last_updated' => now(),
            ])->save();
        }
    }

    private function seedClaimForDelivery(
        Delivery $delivery,
        User $officeUser,
        User $gsoUser,
        string $claimType,
        string $claimLocation,
        string $createRemarks,
        ?string $resolveRemarks,
    ): void {
        $distributionRecord = DistributionRecord::query()->where('delivery_id', (int) $delivery->delivery_id)->first();
        if (! $distributionRecord instanceof DistributionRecord) {
            return;
        }

        $claimService = app(ClaimService::class);
        $claim = Claim::query()->where('distribution_record_id', (int) $distributionRecord->distribution_record_id)->latest('claims_id')->first();
        if (! $claim instanceof Claim) {
            $claim = $claimService->createClaim($officeUser, [
                'distribution_record_id' => (int) $distributionRecord->distribution_record_id,
                'office_id' => (int) $officeUser->office_id,
                'claim_type' => $claimType,
                'claim_date' => now()->toDateString(),
                'claim_location' => $claimLocation,
                'remarks' => $createRemarks,
                'distribution_record' => $distributionRecord,
            ]);
        }

        if ($resolveRemarks === null) {
            return;
        }

        if (in_array((string) $claim->status, ['RESOLVED', 'REJECTED'], true)) {
            return;
        }

        try {
            $claimService->updateClaimStatus($claim, $gsoUser, [
                'status' => 'RESOLVED',
                'remarks' => $resolveRemarks,
            ]);
        } catch (Throwable $exception) {
            Log::warning('LongitudinalDemoWorkflowSeeder selective claim resolution skipped.', [
                'claims_id' => (int) $claim->claims_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function seedAlerts(User $gsoUser, PurchaseOrder $purchaseOrder, Delivery $delivery): void
    {
        $alertService = app(AlertService::class);

        $lowStockItemId = (int) Item::query()->where('item_name', 'Printer Ink')->value('item_id');
        $lowStockInventory = Inventory::query()->where('item_id', $lowStockItemId)->first();

        if ($lowStockInventory instanceof Inventory) {
            $existing = Alert::query()->where('alert_type', 'LOW_STOCK')->where('office_id', $gsoUser->office_id)->first();
            if (! $existing instanceof Alert) {
                $alertService->create($gsoUser, [
                    'alert_type' => 'LOW_STOCK',
                    'severity' => 'HIGH',
                    'office_id' => (int) $gsoUser->office_id,
                    'alert_message' => 'Printer ink is at low stock and needs replenishment soon.',
                    'detected_at' => now(),
                ]);
            }
        }

        $existingDelayAlert = Alert::query()->where('alert_type', 'DELAYED_DELIVERY')->where('purchase_order_id', (int) $purchaseOrder->purchase_order_id)->first();
        if (! $existingDelayAlert instanceof Alert) {
            $alertService->create($gsoUser, [
                'alert_type' => 'DELAYED_DELIVERY',
                'severity' => 'MEDIUM',
                'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                'supplier_id' => (int) $delivery->supplier_id,
                'office_id' => (int) $gsoUser->office_id,
                'alert_message' => 'Delivery was received after the expected date and was partially verified.',
                'detected_at' => now(),
            ]);
        }
    }

    private function seedInventoryShowcases(): void
    {
        $showcase = [
            'Bond Paper' => ['stock_on_hand' => 160, 'available_stock' => 100, 'threshold' => 30],
            'Printer Ink' => ['stock_on_hand' => 6, 'available_stock' => 2, 'threshold' => 8],
            'Toner Cartridge' => ['stock_on_hand' => 4, 'available_stock' => 3, 'threshold' => 3],
        ];

        foreach ($showcase as $itemName => $values) {
            $item = Item::query()->where('item_name', $itemName)->first();
            if (! $item instanceof Item) {
                continue;
            }

            Inventory::query()->updateOrCreate(
                ['item_id' => (int) $item->item_id],
                [
                    'stock_on_hand' => (float) $values['stock_on_hand'],
                    'available_stock' => (float) $values['available_stock'],
                    'reserved_quantity' => max((float) $values['stock_on_hand'] - (float) $values['available_stock'], 0),
                    'released_quantity' => 0,
                    'low_stock_threshold' => (float) $values['threshold'],
                    'last_updated' => now(),
                ],
            );
        }
    }

    /**
     * @param  Collection<string, int>  $items
     */
    private function seedInventory(Collection $items): void
    {
        $inventoryRows = [
            'Bond Paper' => [220, 200, 25],
            'Ballpen' => [80, 70, 20],
            'Printer Ink' => [4, 4, 8],
            'Mouse' => [10, 10, 6],
            'Logbook' => [5, 5, 8],
            'Rubbing Alcohol' => [6, 6, 8],
            'Detergent Powder' => [8, 8, 10],
            'Safety Gloves' => [3, 3, 5],
            'Flash Drive' => [5, 5, 4],
            'Toner Cartridge' => [2, 2, 3],
            'Folder' => [24, 18, 10],
        ];

        foreach ($inventoryRows as $itemName => [$stockOnHand, $availableStock, $lowStockThreshold]) {
            $itemId = (int) ($items->get($itemName) ?? 0);
            if ($itemId <= 0) {
                throw new RuntimeException(sprintf('Missing item for inventory seed row: %s', $itemName));
            }

            Inventory::query()->updateOrCreate(
                ['item_id' => $itemId],
                [
                    'stock_on_hand' => $stockOnHand,
                    'available_stock' => $availableStock,
                    'reserved_quantity' => max((float) $stockOnHand - (float) $availableStock, 0),
                    'released_quantity' => 0,
                    'low_stock_threshold' => $lowStockThreshold,
                    'last_updated' => now(),
                ],
            );
        }
    }

    private function normalizeGsoLinkedDemoData(User $gsoUser): void
    {
        $distributionService = app(DistributionRecordService::class);

        $records = DistributionRecord::query()
            ->with(['supplyRequest.items', 'items', 'claims'])
            ->where(function ($query): void {
                $query->where('status', 'RELEASED')
                    ->orWhere('status', 'RECEIVED');
            })
            ->orderBy('distribution_record_id')
            ->get();

        foreach ($records as $record) {
            if (! $record instanceof DistributionRecord) {
                continue;
            }

            if ($record->items->isNotEmpty()) {
                continue;
            }

            $supplyItems = $record->supplyRequest?->items ?? collect();
            foreach ($supplyItems as $supplyItem) {
                $inventory = Inventory::query()->where('item_id', (int) $supplyItem->item_id)->first();
                if (! $inventory instanceof Inventory) {
                    continue;
                }

                $releaseQty = min((float) $supplyItem->quantity_requested, max((float) $inventory->available_stock, 0));
                if ($releaseQty <= 0) {
                    continue;
                }

                try {
                    $distributionService->addDistributionItem($record, $gsoUser, [
                        'item_id' => (int) $supplyItem->item_id,
                        'received_by' => $record->received_by,
                        'item_name_snapshot' => (string) $supplyItem->item_name_snapshot,
                        'quantity_released' => round($releaseQty, 2),
                        'unit' => 'piece',
                        'remarks' => 'Seeder normalization: linked released item from supply request context.',
                    ]);
                } catch (Throwable $exception) {
                    Log::warning('LongitudinalDemoWorkflowSeeder distribution item normalization skipped.', [
                        'distribution_record_id' => (int) $record->distribution_record_id,
                        'item_id' => (int) $supplyItem->item_id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

    }
}
