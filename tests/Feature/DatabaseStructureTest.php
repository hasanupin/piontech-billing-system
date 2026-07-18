<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseStructureTest extends TestCase
{
    use RefreshDatabase;

    public function testAllCoreTablesAndColumnsExist(): void
    {
        foreach (['packages', 'clusters', 'customers', 'transactions', 'officer_deposits'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] is missing.");
        }

        $this->assertTrue(Schema::hasColumn('customers', 'whatsapp_number'));
    }

    public function testMigrationsAndSeedersRunSuccessfully(): void
    {
        $this->seed();

        $this->assertSame(5, User::count());
        $this->assertSame(3, Package::count());
        $this->assertSame(3, Cluster::count());
        $this->assertSame(50, Customer::count());
        $this->assertGreaterThan(0, Customer::count());
    }

    public function testPreventsDuplicateTransactionPerCustomerPerPeriod(): void
    {
        $transaction = Transaction::factory()->create();

        $this->expectException(QueryException::class);

        Transaction::factory()->create([
            'customer_id' => $transaction->customer_id,
            'period' => $transaction->period,
        ]);
    }

    public function testRestrictsDeleteOfClusterWithCustomers(): void
    {
        $cluster = Cluster::factory()->create();
        Customer::factory()->create(['cluster_id' => $cluster->id]);

        $this->expectException(QueryException::class);

        $cluster->delete();
    }
}
