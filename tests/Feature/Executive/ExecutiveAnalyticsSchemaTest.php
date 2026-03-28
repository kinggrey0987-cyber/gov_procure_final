<?php

namespace Tests\Feature\Executive;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExecutiveAnalyticsSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_phase_six_tables_match_required_field_names(): void
    {
        $this->assertTrue(Schema::hasColumns('anomalies', [
            'anomaly_id',
            'anomaly_type',
            'reference_type',
            'reference_id',
            'item_id',
            'office_id',
            'detected_value',
            'expected_value',
            'threshold_value',
            'z_score',
            'detection_method',
            'severity',
            'status',
            'detected_at',
            'remarks',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('alerts', [
            'alerts_id',
            'alert_type',
            'severity',
            'anomaly_id',
            'supply_request_id',
            'purchase_order_id',
            'supplier_id',
            'office_id',
            'alert_message',
            'detected_at',
            'reviewed_by',
            'status',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('public_disclosures', [
            'disclosure_id',
            'title',
            'type',
            'reference_type',
            'reference_id',
            'description',
            'published_by',
            'published_at',
            'file_path',
            'status',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('alerts', 'alert_id'));
        $this->assertFalse(Schema::hasColumn('public_disclosures', 'disclosures_id'));
    }
}
