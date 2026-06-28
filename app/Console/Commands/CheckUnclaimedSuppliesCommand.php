<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\DistributionRecord;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckUnclaimedSuppliesCommand extends Command
{
    protected $signature = 'supply:check-unclaimed
                            {--days=2 : Number of days after release before triggering an alert}';

    protected $description = 'Create alerts and notifications for supplies that have been released but not claimed by the office within the configured threshold.';

    public function handle(NotificationService $notificationService): int
    {
        $thresholdDays = max((int) $this->option('days'), 1);
        $cutoffDate = Carbon::now()->subDays($thresholdDays)->endOfDay();

        $overdueDistributions = DistributionRecord::query()
            ->released()
            ->where('release_date', '<=', $cutoffDate)
            ->with(['office', 'supplyRequest.requester'])
            ->get();

        if ($overdueDistributions->isEmpty()) {
            $this->info('No unclaimed supplies found beyond the '.$thresholdDays.'-day threshold.');

            return self::SUCCESS;
        }

        $createdAlerts = 0;

        foreach ($overdueDistributions as $record) {
            /** @var DistributionRecord $record */
            $officeId = (int) $record->office_id;
            $supplyRequestId = $record->supply_request_id ? (int) $record->supply_request_id : null;
            $distributionCode = (string) $record->distribution_code;
            $officeName = (string) ($record->office?->name ?? 'Unknown Office');
            $releaseDate = optional($record->release_date)?->toDateString() ?? 'unknown date';

            // Skip if an active alert of this type already exists for this distribution
            $existingAlert = Alert::query()
                ->where('alert_type', 'UNCLAIMED_SUPPLY')
                ->where('office_id', $officeId)
                ->where('supply_request_id', $supplyRequestId)
                ->whereIn('status', ['ACTIVE', 'PENDING'])
                ->exists();

            if ($existingAlert) {
                continue;
            }

            $alertMessage = sprintf(
                'Supplies from distribution %s (released %s) have not been claimed by %s for %d+ days. Please confirm receipt or contact the office.',
                $distributionCode,
                $releaseDate,
                $officeName,
                $thresholdDays,
            );

            Alert::query()->create([
                'alert_type' => 'UNCLAIMED_SUPPLY',
                'severity' => 'MEDIUM',
                'supply_request_id' => $supplyRequestId,
                'office_id' => $officeId,
                'alert_message' => $alertMessage,
                'detected_at' => now(),
                'status' => 'ACTIVE',
            ]);

            // Notify the office user who made the supply request
            $notificationService->notifyDistributionOfficeRecipient(
                record: $record,
                title: 'Unclaimed Supply Reminder',
                message: sprintf(
                    'Your supplies from distribution %s (released %s) have not been confirmed as received. Please claim your supplies at the GSO or confirm receipt in the system.',
                    $distributionCode,
                    $releaseDate,
                ),
                notificationType: 'UNCLAIMED_SUPPLY_REMINDER',
            );

            $createdAlerts++;
        }

        $this->info(sprintf(
            'Checked %d overdue distribution(s). Created %d new unclaimed supply alert(s).',
            $overdueDistributions->count(),
            $createdAlerts,
        ));

        return self::SUCCESS;
    }
}
