<?php

namespace App\Console\Commands;

use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Services\Procurement\ConsolidationTraceabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncConsolidatedSupplyRequestsCommand extends Command
{
    protected $signature = 'procurement:sync-consolidated-statuses';

    protected $description = 'Sync the status of all source supply requests belonging to returned or rejected consolidated purchase requests';

    public function handle(ConsolidationTraceabilityService $traceabilityService): int
    {
        $this->info('Scanning consolidated purchase requests with status RETURNED_FOR_REVISION or REJECTED...');

        $purchaseRequests = PurchaseRequest::query()
            ->whereIn('status', ['RETURNED_FOR_REVISION', 'REJECTED'])
            ->get();

        $updatedCount = 0;

        foreach ($purchaseRequests as $pr) {
            $sourceRequests = $traceabilityService->getSourceSupplyRequests($pr);

            if ($sourceRequests->count() < 2) {
                continue;
            }

            $targetStatus = (string) $pr->status === 'RETURNED_FOR_REVISION' ? 'DRAFT' : 'REJECTED';
            $remarks = sprintf(
                'Linked consolidated purchase request %s was %s by procurement. %s',
                (string) $pr->pr_number,
                strtolower(str_replace('_', ' ', (string) $pr->status)),
                (string) ($pr->remarks ?? '')
            );

            foreach ($sourceRequests as $supplyRequest) {
                if ((string) $supplyRequest->status === 'FOR_PROCUREMENT') {
                    DB::transaction(function () use ($supplyRequest, $targetStatus, $remarks, &$updatedCount): void {
                        $oldStatus = (string) $supplyRequest->status;

                        $supplyRequest->forceFill([
                            'status' => $targetStatus,
                        ])->save();

                        StatusHistory::query()->create([
                            'reference_type' => SupplyRequest::class,
                            'reference_id' => (int) $supplyRequest->supply_request_id,
                            'old_status' => $oldStatus,
                            'new_status' => $targetStatus,
                            'changed_by_user_id' => $supplyRequest->requested_by,
                            'remarks' => $remarks,
                            'changed_at' => now(),
                        ]);

                        $updatedCount++;
                    });

                    $this->line(sprintf(' - Updated %s from FOR_PROCUREMENT to %s (Linked to %s)', $supplyRequest->request_code, $targetStatus, $pr->pr_number));
                }
            }
        }

        $this->info(sprintf('Successfully synced %d supply request(s).', $updatedCount));

        return Command::SUCCESS;
    }
}
