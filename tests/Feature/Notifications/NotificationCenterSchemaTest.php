<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationCenterSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_table_contains_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));

        $this->assertTrue(Schema::hasColumns('notifications', [
            'notification_id',
            'user_id',
            'title',
            'message',
            'notification_type',
            'reference_type',
            'reference_id',
            'is_read',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('notifications', 'read_at'));
    }
}
