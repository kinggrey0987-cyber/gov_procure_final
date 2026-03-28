<?php

namespace App\Services\Analytics;

use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\Office;

class ExecutiveMonitoringService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'open_anomaly_count' => Anomaly::query()->byStatus('OPEN')->count(),
            'in_review_anomaly_count' => Anomaly::query()->byStatus('IN_REVIEW')->count(),
            'high_severity_anomaly_count' => Anomaly::query()->bySeverity('HIGH')->count(),
            'open_alert_count' => Alert::query()->byStatus('OPEN')->count(),
            'in_review_alert_count' => Alert::query()->byStatus('IN_REVIEW')->count(),
            'high_severity_alert_count' => Alert::query()->bySeverity('HIGH')->count(),
            'cross_office_anomaly_count' => Anomaly::query()->whereNotNull('office_id')->distinct('office_id')->count('office_id'),
            'office_breakdown' => Office::query()
                ->select('offices.id', 'offices.name')
                ->withCount(['anomalies as anomaly_count', 'alerts as alert_count'])
                ->orderBy('offices.name')
                ->get(),
        ];
    }
}
