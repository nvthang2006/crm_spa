<?php

namespace Tests\Feature;

use App\Livewire\Admin\DashboardPage;
use App\Models\CommissionEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_renders_operational_kpis_and_staff_performance(): void
    {
        $employee = Employee::factory()->create(['name' => 'KTV Hằng', 'job_title' => 'Kỹ thuật viên']);
        $customer = Customer::factory()->create(['name' => 'Chị Lan', 'phone' => '0901234567']);
        $offering = Offering::factory()->create(['name' => 'Chăm sóc da chuyên sâu', 'price' => 300000, 'type' => 'service']);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
            'paid_at' => now(),
            'total_received' => 300000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'offering_id' => $offering->id,
            'employee_id' => $employee->id,
            'unit_price' => 300000,
            'line_total' => 300000,
        ]);

        CommissionEntry::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $employee->id,
            'amount' => 30000,
            'rate' => 10,
            'calculation_type' => 'percentage',
            'occurred_at' => now(),
            'status' => 'pending',
        ]);

        Livewire::test(DashboardPage::class)
            ->assertOk()
            ->assertViewHas('revenueToday', 300000)
            ->assertViewHas('commissionToday', 30000)
            ->assertViewHas('sessionsRetailToday', 1)
            ->assertViewHas('sessionsPackageToday', 0)
            ->assertSee('Nhập ca làm cuối ngày')
            ->assertSee('KTV Hằng')
            ->assertSee('300.000 ₫')
            ->assertSee('30.000 ₫');
    }
}
