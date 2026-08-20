<?php

namespace Tests\Unit;

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful database connection (Table 36: TC-WData001).
     */
    public function test_database_connection_is_established_successfully(): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();

        $this->assertNotNull($pdo, 'PDO connection instance should not be null.');
        $this->assertTrue($connection->getDatabaseName() !== '', 'Database name should be resolved.');
        
        $result = DB::select('SELECT 1 as is_connected');
        $this->assertSame(1, (int) ($result[0]->is_connected ?? 0));
    }

    /**
     * Test database transaction rollback on fatal exception (Table 36: TC-WData002).
     */
    public function test_database_transaction_rollbacks_on_exception(): void
    {
        $initialCount = Office::query()->count();

        try {
            DB::transaction(function (): void {
                Office::query()->create([
                    'name' => 'Temporary Rolled Back Office',
                    'code' => 'TEMP_ROLLBACK',
                    'description' => 'Will be rolled back',
                    'is_active' => true,
                ]);

                throw new \RuntimeException('Simulated unexpected transaction failure.');
            });
        } catch (\RuntimeException $e) {
            // Expected exception caught
        }

        $this->assertSame($initialCount, Office::query()->count(), 'Database transaction rollback failed to revert changes.');
        $this->assertDatabaseMissing('offices', ['code' => 'TEMP_ROLLBACK']);
    }

    /**
     * Test persistent connection reuse and query execution (Table 36: TC-WData003).
     */
    public function test_database_connection_reusable_and_executes_queries(): void
    {
        $this->assertTrue(DB::connection()->getPdo() instanceof \PDO);

        // Execute successive queries on the active PDO connection
        DB::statement('SELECT 1');
        DB::statement('SELECT 2');

        $this->assertDatabaseCount('offices', 0);
    }
}
