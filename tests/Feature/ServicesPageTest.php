<?php

namespace Tests\Feature;

use App\Livewire\Admin\ServicesPage;
use App\Models\CommissionRule;
use App\Models\Offering;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServicesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_page_renders_with_tabs_and_metrics(): void
    {
        $user = User::factory()->create();
        $category = ServiceCategory::create(['code' => 'MASSAGE', 'name' => 'Massage Body', 'is_active' => true]);
        Offering::create([
            'code' => 'MASS-01',
            'name' => 'Massage bấm huyệt chuyên sâu',
            'service_category_id' => $category->id,
            'type' => 'service',
            'duration_minutes' => 60,
            'price' => 500000,
            'sessions_total' => 1,
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(ServicesPage::class)
            ->assertOk()
            ->assertSee('Dịch vụ')
            ->assertSee('Cấu hình hoa hồng')
            ->assertSee('Danh mục dịch vụ')
            ->assertSee('Chính sách hoa hồng KTV')
            ->assertSee('MASS-01')
            ->assertSee('Massage bấm huyệt chuyên sâu');
    }

    public function test_switching_tabs_shows_respective_content(): void
    {
        $user = User::factory()->create();
        $offering = Offering::create([
            'code' => 'FACIAL-01',
            'name' => 'Chăm sóc da Facial',
            'type' => 'service',
            'duration_minutes' => 60,
            'price' => 600000,
            'sessions_total' => 1,
            'status' => 'active',
        ]);

        CommissionRule::create([
            'offering_id' => $offering->id,
            'beneficiary_type' => 'primary_technician',
            'basis' => 'payment',
            'calculation_type' => 'percentage',
            'value' => 15,
            'starts_on' => today(),
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ServicesPage::class)
            ->assertSet('activeTab', 'offerings')
            ->call('switchTab', 'commissions')
            ->assertSet('activeTab', 'commissions')
            ->assertSee('Danh sách quy tắc hoa hồng')
            ->call('switchTab', 'offerings')
            ->assertSet('activeTab', 'offerings')
            ->assertSee('FACIAL-01');
    }

    public function test_filter_by_type_and_category_works(): void
    {
        $user = User::factory()->create();
        $cat1 = ServiceCategory::create(['code' => 'CAT1', 'name' => 'Danh mục 1', 'is_active' => true]);
        $cat2 = ServiceCategory::create(['code' => 'CAT2', 'name' => 'Danh mục 2', 'is_active' => true]);

        Offering::create([
            'code' => 'SRV-1',
            'name' => 'Dịch vụ lẻ 1',
            'service_category_id' => $cat1->id,
            'type' => 'service',
            'duration_minutes' => 45,
            'price' => 300000,
            'sessions_total' => 1,
            'status' => 'active',
        ]);

        Offering::create([
            'code' => 'PKG-1',
            'name' => 'Gói liệu trình VIP',
            'service_category_id' => $cat2->id,
            'type' => 'package',
            'duration_minutes' => 90,
            'price' => 2000000,
            'sessions_total' => 10,
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(ServicesPage::class)
            ->call('filterByType', 'package')
            ->assertSee('Gói liệu trình VIP')
            ->assertDontSee('Dịch vụ lẻ 1')
            ->call('filterByType', 'service')
            ->assertSee('Dịch vụ lẻ 1')
            ->assertDontSee('Gói liệu trình VIP')
            ->call('resetFilters')
            ->assertSet('typeFilter', 'all')
            ->assertSet('categoryFilter', 'all')
            ->assertSee('Dịch vụ lẻ 1')
            ->assertSee('Gói liệu trình VIP');
    }

    public function test_modals_open_for_new_offering_and_new_commission(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ServicesPage::class)
            ->assertSet('showForm', false)
            ->call('newOffering', 'service')
            ->assertSet('showForm', true)
            ->assertSet('type', 'service')
            ->set('showForm', false)
            ->call('newCommission')
            ->assertSet('showCommissionForm', true);
    }

    public function test_creating_and_updating_offering_automatically_persists_primary_commission_rule(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ServicesPage::class)
            ->call('newOffering', 'service')
            ->set('code', 'MASSAGE-THAI')
            ->set('name', 'Massage Thái Cổ Truyền')
            ->set('price', 350000)
            ->set('durationMinutes', 60)
            ->set('offeringCommissionEnabled', true)
            ->set('offeringCommissionType', 'percentage')
            ->set('offeringCommissionValue', '15')
            ->call('saveOffering')
            ->assertHasNoErrors();

        $offering = Offering::where('code', 'MASSAGE-THAI')->firstOrFail();
        $this->assertDatabaseHas('commission_rules', [
            'offering_id' => $offering->id,
            'beneficiary_type' => 'primary_technician',
            'calculation_type' => 'percentage',
            'value' => '15.00',
            'is_active' => true,
        ]);

        // Edit to fixed commission
        Livewire::actingAs($user)
            ->test(ServicesPage::class)
            ->call('editOffering', $offering->id)
            ->assertSet('offeringCommissionEnabled', true)
            ->assertSet('offeringCommissionType', 'percentage')
            ->assertSet('offeringCommissionValue', '15.00')
            ->set('offeringCommissionType', 'fixed')
            ->set('offeringCommissionValue', '60000')
            ->call('saveOffering')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('commission_rules', [
            'offering_id' => $offering->id,
            'beneficiary_type' => 'primary_technician',
            'calculation_type' => 'fixed',
            'value' => '60000.00',
            'is_active' => true,
        ]);
    }
}
