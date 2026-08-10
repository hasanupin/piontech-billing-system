<?php

namespace Tests\Feature;

use App\Enums\RecipientType;
use App\Filament\Resources\CommissionRecipients\CommissionRecipientResource;
use App\Filament\Resources\CommissionRecipients\Pages\CreateCommissionRecipient;
use App\Filament\Resources\CommissionRecipients\Pages\EditCommissionRecipient;
use App\Models\Cluster;
use App\Models\CommissionRecipient;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommissionRecipientModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanAccessCommissionRecipientResource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(CommissionRecipientResource::getUrl('index'))->assertOk();
    }

    public function testFieldOfficerCannotAccessCommissionRecipientResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(CommissionRecipientResource::getUrl('index'))->assertForbidden();
    }

    public function testCreatesExternalRecipientWithOwnContactData(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCommissionRecipient::class)
            ->fillForm([
                'type' => RecipientType::External->value,
                'name' => 'Pak Referal',
                'address' => 'Dusun Krajan',
                'whatsapp_number' => '628123456789',
                'commission_percent' => 4,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipient = CommissionRecipient::firstWhere('name', 'Pak Referal');

        $this->assertNotNull($recipient);
        $this->assertNull($recipient->customer_id);
        $this->assertSame('Pak Referal', $recipient->display_name);
        $this->assertSame('Dusun Krajan', $recipient->display_address);
        $this->assertSame('628123456789', $recipient->display_whatsapp);
    }

    public function testCustomerTypeRecipientStoresNullContactAndMirrorsCustomer(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Budi',
            'address' => 'Dusun Sumber',
            'whatsapp_number' => '628999888777',
        ]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCommissionRecipient::class)
            ->fillForm([
                'type' => RecipientType::Customer->value,
                'customer_id' => $customer->id,
                'commission_percent' => 4,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipient = CommissionRecipient::firstWhere('customer_id', $customer->id);

        $this->assertNotNull($recipient);
        // Mirror: kolom kontak sendiri sengaja kosong.
        $this->assertNull($recipient->name);
        $this->assertNull($recipient->address);
        $this->assertNull($recipient->whatsapp_number);
        $this->assertSame('Budi', $recipient->display_name);
        $this->assertSame('Dusun Sumber', $recipient->display_address);
        $this->assertSame('628999888777', $recipient->display_whatsapp);
    }

    public function testEditingCustomerTypeRecipientKeepsContactColumnsNull(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Budi',
            'address' => 'Dusun Sumber',
            'whatsapp_number' => '628999888777',
        ]);
        $recipient = CommissionRecipient::factory()->customerType($customer)->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EditCommissionRecipient::class, ['record' => $recipient->getKey()])
            // Field read-only tetap terlihat terisi dari pelanggannya.
            ->assertFormSet([
                'name' => 'Budi',
                'address' => 'Dusun Sumber',
                'whatsapp_number' => '628999888777',
            ])
            ->fillForm(['commission_percent' => 6])
            ->call('save')
            ->assertHasNoFormErrors();

        $recipient->refresh();

        $this->assertEquals(6, $recipient->commission_percent);
        // Mirror tidak boleh bocor jadi salinan saat edit.
        $this->assertNull($recipient->name);
        $this->assertNull($recipient->address);
        $this->assertNull($recipient->whatsapp_number);
    }

    public function testMirrorFollowsCustomerUpdate(): void
    {
        $customer = Customer::factory()->create(['whatsapp_number' => '628111111111']);
        $recipient = CommissionRecipient::factory()->customerType($customer)->create();

        $customer->update(['whatsapp_number' => '628222222222', 'name' => 'Budi Baru']);

        $this->assertSame('628222222222', $recipient->fresh()->display_whatsapp);
        $this->assertSame('Budi Baru', $recipient->fresh()->display_name);
    }

    public function testCommissionPercentDefaultsFromSettingsWithoutTouchingExistingRows(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCommissionRecipient::class)
            ->assertSet('data.commission_percent', 4.0);

        $existing = CommissionRecipient::factory()->create(['commission_percent' => 4]);

        Setting::set(Setting::DEFAULT_COMMISSION_PERCENT, '7');

        Livewire::test(CreateCommissionRecipient::class)
            ->assertSet('data.commission_percent', 7.0);

        // Penerima lama tidak ikut berubah.
        $this->assertEquals(4, $existing->fresh()->commission_percent);
    }

    public function testDisplayNameStaysVisibleForFieldOfficerOutsideCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);
        // Pelanggan cluster lain — kena global scope Customer untuk petugas.
        $foreign = Customer::factory()->create(['name' => 'Pelanggan Luar']);
        $recipient = CommissionRecipient::factory()->customerType($foreign)->create();

        $this->actingAs($officer);

        $this->assertSame('Pelanggan Luar', $recipient->fresh()->display_name);
    }

    public function testDeletingRecipientNullsCustomerReferral(): void
    {
        $recipient = CommissionRecipient::factory()->create();
        $customer = Customer::factory()->create(['referral_id' => $recipient->id]);

        $recipient->delete();

        $this->assertNull($customer->fresh()->referral_id);
    }

    public function testActiveScopeExcludesInactiveRecipients(): void
    {
        CommissionRecipient::factory()->create();
        CommissionRecipient::factory()->inactive()->create();

        $this->assertSame(1, CommissionRecipient::active()->count());
    }
}
