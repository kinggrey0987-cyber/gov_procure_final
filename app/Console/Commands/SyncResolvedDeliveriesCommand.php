<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Services\Delivery\DeliveryVerificationService;
use Illuminate\Console\Command;

class SyncResolvedDeliveriesCommand extends Command
{
    protected $signature = 'deliveries:sync-resolved';

    protected $description = 'Scan verified follow-up deliveries and automatically resolve any partially received parent deliveries';

    public function handle(DeliveryVerificationService $verificationService): int
    {
        $this->info('Scanning verified deliveries to resolve parent partial deliveries...');

        $verifiedDeliveries = Delivery::query()
            ->where('receipt_status', 'VERIFIED')
            ->with(['purchaseOrder.purchaseRequest.sourceDelivery', 'purchaseOrder.purchaseRequest.sourceClaim.distributionRecord.delivery'])
            ->get();

        $resolvedCount = 0;

        foreach ($verifiedDeliveries as $delivery) {
            $initialStatus = null;
            $pr = $delivery->purchaseOrder?->purchaseRequest;
            $parent = $pr?->sourceDelivery ?? $pr?->sourceClaim?->distributionRecord?->delivery;

            if ($parent && (string) $parent->receipt_status === 'PARTIALLY_RECEIVED') {
                $verificationService->resolveSourcePartialDeliveryIfFollowUpCompleted($delivery);
                if ((string) $parent->fresh()->receipt_status === 'VERIFIED') {
                    $this->info(sprintf('Resolved parent delivery %s from follow-up delivery %s.', $parent->delivery_code, $delivery->delivery_code));
                    $resolvedCount++;
                }
            }
        }

        $this->info(sprintf('Done. Resolved %d parent deliveries.', $resolvedCount));

        return Command::SUCCESS;
    }
}
