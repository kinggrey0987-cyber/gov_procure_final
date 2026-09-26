<?php

namespace Tests\Feature\Executive;

use App\Enums\UserRole;
use App\Models\Anomaly;
use App\Models\Notification;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\AnomalyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnomalyNotificationFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_anomaly_humanize_type_converts_database_keys_to_readable_labels(): void
    {
        $this->assertEquals('Price Anomaly', Anomaly::humanizeType(Anomaly::TYPE_PRICE_Z_SCORE));
        $this->assertEquals('Excessive Quantity Anomaly', Anomaly::humanizeType(Anomaly::TYPE_EXCESSIVE_QUANTITY));
        $this->assertEquals('Frequent Request Spike', Anomaly::humanizeType(Anomaly::TYPE_FREQUENT_REQUEST_WINDOW));
        $this->assertEquals('PPMP Over-Request', Anomaly::humanizeType(Anomaly::TYPE_PPMP_OVER_REQUEST));
        $this->assertEquals('PPMP Early Depletion', Anomaly::humanizeType(Anomaly::TYPE_PPMP_EARLY_DEPLETION));
    }

    public function test_recording_anomaly_generates_humanized_notification_message(): void
    {
        $execRole = Role::query()->firstOrCreate(['code' => UserRole::EXECUTIVE_OFFICER->value], ['name' => 'Executive Officer']);
        $office = Office::query()->create(['name' => 'Mayor Office', 'code' => 'MO', 'status' => 'ACTIVE']);
        $execUser = User::factory()->create(['role_id' => $execRole->id, 'office_id' => $office->id]);

        $service = app(AnomalyService::class);
        $anomaly = $service->createOrUpdateSystemDetected([
            'anomaly_type' => Anomaly::TYPE_PRICE_Z_SCORE,
            'reference_type' => Office::class,
            'reference_id' => $office->id,
            'office_id' => $office->id,
            'detected_value' => 1200,
            'expected_value' => 800,
            'severity' => 'HIGH',
            'detection_method' => 'PRICE_Z_SCORE',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $execUser->id,
            'notification_type' => 'EXEC_ANOMALY_DETECTED',
            'reference_type' => Anomaly::class,
            'reference_id' => $anomaly->anomaly_id,
            'message' => 'Price Anomaly was detected and needs executive review.',
        ]);
    }

    public function test_notification_accessor_dynamically_translates_legacy_raw_anomaly_messages(): void
    {
        $execRole = Role::query()->firstOrCreate(['code' => UserRole::EXECUTIVE_OFFICER->value], ['name' => 'Executive Officer']);
        $office = Office::query()->create(['name' => 'Mayor Office', 'code' => 'MO', 'status' => 'ACTIVE']);
        $user = User::factory()->create(['role_id' => $execRole->id, 'office_id' => $office->id]);

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'title' => 'New Anomaly Detected',
            'message' => 'Anomaly PRICE_Z_SCORE was detected and needs executive review.',
            'notification_type' => 'EXEC_ANOMALY_DETECTED',
            'reference_type' => Anomaly::class,
            'reference_id' => 999,
            'is_read' => false,
        ]);

        $this->assertEquals(
            'Price Anomaly was detected and needs executive review.',
            $notification->fresh()->message
        );
    }
}
