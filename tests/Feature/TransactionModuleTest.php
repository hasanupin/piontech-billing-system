<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Pages\MonthlyBilling;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanAccessTransactionResource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(TransactionResource::getUrl('index'))->assertOk();
    }

    public function testFieldOfficerCanAccessTransactionResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(TransactionResource::getUrl('index'))->assertOk();
    }

    public function testPrefillsNominalFromCustomerBillingAmount(): void
    {
        $customer = Customer::factory()->create(['billing_amount' => 110000]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTransaction::class)
            ->set('data.customer_id', $customer->id)
            ->assertSet('data.billed_amount', '110.000,00');
    }

    public function testAllowsOverridingPrefilledNominal(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $package = Package::factory()->create(['default_price' => 110000]);
        $customer = Customer::factory()->create(['package_id' => $package->id]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'payment_method' => PaymentMethod::Cash->value,
                'officer_id' => $officer->id,
                'billed_amount' => '100000',
                'paid_amount' => '100000',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('100000.00', Transaction::first()->billed_amount);
    }

    public function testHidesTransferOptionFromFieldOfficer(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);
        $this->actingAs($officer);

        Livewire::test(CreateTransaction::class)
            ->assertFormFieldExists('payment_method', function ($field): bool {
                return array_keys($field->getOptions()) === [PaymentMethod::Cash->value];
            });
    }

    public function testTransferTransactionHasNullOfficerAndExcludedFromHeldByOfficers(): void
    {
        Transaction::factory()->transfer()->create(['paid_amount' => 500000]);

        $summary = app(BillingService::class)->monthlySummary(now());

        $this->assertSame(0.0, $summary['held_by_officers']); // transfer tidak lewat petugas
        $this->assertSame(500000.0, $summary['transfer']);
    }

    public function testPreventsDuplicateTransactionSameCustomerSamePeriod(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $customer = Customer::factory()->create();
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'period' => now()->startOfMonth(),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'payment_method' => PaymentMethod::Cash->value,
                'officer_id' => $officer->id,
                'billed_amount' => '100000',
                'paid_amount' => '100000',
            ])
            ->call('create')
            ->assertHasFormErrors(['customer_id']);
    }

    public function testMonthlyBillingPageShowsBillableCustomersAndUnpaidFilter(): void
    {
        $paidCustomer = Customer::factory()->create();
        Transaction::factory()->create([
            'customer_id' => $paidCustomer->id,
            'period' => now()->startOfMonth(),
            'status' => 'paid',
        ]);
        $unpaidCustomer = Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MonthlyBilling::class)
            ->assertCanSeeTableRecords([$paidCustomer, $unpaidCustomer])
            ->filterTable('unpaid')
            ->assertCanSeeTableRecords([$unpaidCustomer])
            ->assertCanNotSeeTableRecords([$paidCustomer]);
    }
}
