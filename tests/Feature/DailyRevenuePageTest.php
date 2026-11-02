<?php

namespace Tests\Feature;

use App\Livewire\Admin\DailyRevenuePage;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Offering;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\DailyRevenueService;
use App\Services\PayrollService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DailyRevenuePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_daily_entry_page(): void
    {
        $this->get(route('daily-revenue'))->assertRedirect(route('login'));
    }

    public function test_two_services_for_one_customer_save_correct_revenue_and_each_employees_commission(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();
        $facial = Offering::factory()->create(['type' => 'service', 'price' => 700000]);
        $shampoo = Offering::factory()->create(['type' => 'service']);
        CommissionRule::factory()->create(['offering_id' => $facial->id, 'value' => 10]);
        CommissionRule::factory()->create(['offering_id' => $shampoo->id, 'calculation_type' => 'fixed', 'value' => 20000]);
        $row = $this->customerRow($customer, $facial, $employee, '500000');
        $row['services'][] = $this->serviceRow($shampoo, $otherEmployee, '100000');

        Livewire::test(DailyRevenuePage::class)->set('customers', [$row])
            ->assertViewHas('draft', ['customers' => 1, 'revenue' => 600000, 'commission' => 70000])
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false)
            ->assertSee('Đã ghi nhận 1 khách, 600.000 ₫.')
            ->assertViewHas('summary', ['customers' => 1, 'revenue' => 600000, 'commission' => 70000])
            ->assertViewHas('staff', fn (array $staff): bool => $staff[$employee->id]['revenue'] === 500000 && $staff[$employee->id]['commission'] === 50000 && $staff[$otherEmployee->id]['revenue'] === 100000 && $staff[$otherEmployee->id]['commission'] === 20000);

        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('commission_entries', ['employee_id' => $employee->id, 'amount' => 50000, 'rate' => 10]);
        $this->assertDatabaseHas('commission_entries', ['employee_id' => $otherEmployee->id, 'amount' => 20000, 'calculation_type' => 'fixed']);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'total_spend' => 600000]);
        $this->assertDatabaseHas('invoice_items', ['offering_id' => $facial->id, 'line_total' => 500000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.paid']);
        $this->get(route('daily-revenue'))->assertOk()->assertSee('Nhập cuối ngày')->assertSee('600.000');
    }

    public function test_repeated_service_lines_and_replayed_submission_do_not_lose_or_duplicate_commission(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'calculation_type' => 'fixed', 'value' => 20000]);
        $row = $this->customerRow($customer, $offering, $employee, '100000');
        $row['services'][] = $this->serviceRow($offering, $employee, '150000');

        $component = Livewire::test(DailyRevenuePage::class)->set('customers', [$row]);
        $component->call('save')->assertHasNoErrors()->call('save')->assertHasNoErrors();

        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('commission_entries', 2);
        $this->assertSame(40000, (int) CommissionEntry::query()->sum('amount'));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'total_spend' => 250000]);
    }

    public function test_a_new_customer_is_created_with_the_receipt_and_audit_trail(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $row = $this->newCustomerRow($offering, $employee, 'Chị Lan', '0901234567');

        Livewire::test(DailyRevenuePage::class)->set('customers', [$row])->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('customers', ['name' => 'Chị Lan', 'phone' => '0901234567', 'total_spend' => 100000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.created']);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_a_failure_in_the_second_customer_rolls_back_the_entire_batch(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $first = $this->newCustomerRow($offering, $employee, 'Khách A', '0901234567');
        $second = $this->newCustomerRow($offering, $employee, 'Khách B', '0901234567');

        Livewire::test(DailyRevenuePage::class)->set('customers', [$first, $second])->call('save')->assertHasErrors(['customers.1.phone']);

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_backdated_revenue_uses_the_rules_and_payroll_period_of_the_selected_day(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setDate(2026, 10, 5));
        $customer = Customer::factory()->create(['last_visit_at' => '2026-10-04 10:00:00']);
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $oldRule = CommissionRule::factory()->create(['offering_id' => $offering->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'value' => 10]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'starts_on' => '2026-10-01', 'value' => 25]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'starts_on' => '2026-09-01', 'value' => 50, 'is_active' => false]);
        $row = $this->customerRow($customer, $offering, $employee, '500000');

        Livewire::test(DailyRevenuePage::class)->set('date', '2026-09-30')->set('customers', [$row])
            ->assertViewHas('draft', ['customers' => 1, 'revenue' => 500000, 'commission' => 50000])
            ->call('save')->assertHasNoErrors()
            ->assertViewHas('summary', ['customers' => 1, 'revenue' => 500000, 'commission' => 50000]);

        $this->assertSame('2026-09-30', Invoice::query()->firstOrFail()->paid_at->toDateString());
        $this->assertSame('2026-09-30', Invoice::query()->firstOrFail()->payments()->firstOrFail()->paid_at->toDateString());
        $this->assertSame('2026-09-30', CommissionEntry::query()->firstOrFail()->occurred_at->toDateString());
        $this->assertSame('2026-10-04', $customer->fresh()->last_visit_at->toDateString());
        $oldRule->update(['value' => 80]);
        $this->assertDatabaseHas('commission_entries', ['commission_rule_id' => $oldRule->id, 'rate' => 10, 'amount' => 50000]);
        $period = app(PayrollService::class)->generate('2026-09-01', '2026-09-30');
        $this->assertSame(50000, (int) $period->total_commission);
    }

    public function test_a_specific_beneficiary_receives_commission_while_revenue_belongs_to_the_performer(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $beneficiary = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'specific_employee', 'beneficiary_employee_id' => $beneficiary->id, 'value' => 15]);

        Livewire::test(DailyRevenuePage::class)->set('customers', [$this->customerRow($customer, $offering, $employee, '200000')])
            ->assertViewHas('draft', ['customers' => 1, 'revenue' => 200000, 'commission' => 30000])
            ->call('save')->assertHasNoErrors()
            ->assertViewHas('staff', fn (array $staff): bool => $staff[$employee->id]['revenue'] === 200000 && $staff[$employee->id]['commission'] === 0 && $staff[$beneficiary->id]['revenue'] === 0 && $staff[$beneficiary->id]['commission'] === 30000);

        $this->assertDatabaseHas('commission_entries', ['employee_id' => $beneficiary->id, 'amount' => 30000]);
    }

    #[TestWith(['-1'])]
    #[TestWith(['1.5'])]
    #[TestWith(['1000000001'])]
    #[TestWith(['abc'])]
    public function test_invalid_amounts_cannot_create_financial_records(string $amount): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);

        Livewire::test(DailyRevenuePage::class)->set('customers', [$this->customerRow($customer, $offering, $employee, $amount)])->call('save')->assertHasErrors(['customers.0.services.0.amount']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_missing_required_fields_are_reported_in_vietnamese(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(DailyRevenuePage::class)->call('save')
            ->assertHasErrors(['customers.0.customer_id', 'customers.0.services.0.offering_id', 'customers.0.services.0.employee_id', 'customers.0.services.0.amount'])
            ->assertSee('Vui lòng nhập khách hàng.')->assertSee('Vui lòng nhập tiền thực thu.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_vietqr_requires_a_reference_before_any_records_are_saved(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $row = $this->customerRow($customer, $offering, $employee, '100000');
        $row['method'] = 'vietqr';

        Livewire::test(DailyRevenuePage::class)->set('customers', [$row])->call('save')->assertHasErrors(['customers.0.reference'])->assertSee('Vui lòng nhập mã giao dịch cho VietQR hoặc POS.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_pos_payment_stores_the_reference_and_real_amount(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $row = $this->customerRow($customer, $offering, $employee, '125000');
        $row['method'] = 'pos';
        $row['reference'] = 'POS-123';

        Livewire::test(DailyRevenuePage::class)->set('customers', [$row])->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('payments', ['method' => 'pos', 'reference' => 'POS-123', 'amount' => 125000]);
    }

    #[TestWith(['inactive_package'])]
    #[TestWith(['inactive_service'])]
    #[TestWith(['inactive_employee'])]
    #[TestWith(['deleted_employee'])]
    public function test_unavailable_services_or_employees_cannot_be_used(string $case): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create(['status' => $case === 'inactive_employee' ? 'inactive' : 'active']);
        $offering = Offering::factory()->create(['type' => str_contains($case, 'package') ? 'package' : 'service', 'status' => str_starts_with($case, 'inactive') ? 'inactive' : 'active']);
        if ($case === 'deleted_employee') {
            $employee->delete();
        }
        $field = str_contains($case, 'employee') ? 'employee_id' : 'offering_id';

        Livewire::test(DailyRevenuePage::class)->set('customers', [$this->customerRow($customer, $offering, $employee, '100000')])->call('save')->assertHasErrors(['customers.0.services.0.'.$field]);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_a_locked_payroll_period_rejects_backdated_entry(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        PayrollPeriod::factory()->create(['period_start' => today()->startOfMonth(), 'period_end' => today()->endOfMonth(), 'status' => 'locked']);

        Livewire::test(DailyRevenuePage::class)->set('customers', [$this->customerRow($customer, $offering, $employee, '100000')])->call('save')->assertHasErrors(['date'])->assertSee('Ngày này thuộc kỳ lương đã khóa.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_future_dates_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);

        Livewire::test(DailyRevenuePage::class)->set('customers', [$this->customerRow($customer, $offering, $employee, '100000')])->set('date', today()->addDay()->toDateString())->call('save')->assertHasErrors(['date']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_refunds_remove_revenue_and_reversed_commission_from_the_summary(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'value' => 10]);
        app(DailyRevenueService::class)->record(today()->toDateString(), [$this->customerRow($customer, $offering, $employee, '100000')], (string) Str::uuid());

        app(RefundService::class)->refund(Invoice::query()->firstOrFail(), 'cash', 'Khách yêu cầu hoàn tiền');

        Livewire::test(DailyRevenuePage::class)->assertViewHas('summary', ['customers' => 0, 'revenue' => 0, 'commission' => 0]);
        $this->assertDatabaseHas('commission_entries', ['status' => 'reversed', 'amount' => 10000]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'total_spend' => 0]);
    }

    public function test_daily_totals_are_not_limited_to_the_current_page_and_count_a_customer_once(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        Invoice::factory()->count(16)->create(['customer_id' => $customer->id, 'type' => 'service', 'status' => 'paid', 'total_received' => 100000, 'paid_at' => now()]);
        Invoice::factory()->create(['status' => 'pending', 'total_received' => 800000, 'paid_at' => now()]);
        Invoice::factory()->create(['status' => 'paid', 'total_received' => 900000, 'paid_at' => today()->subDay()]);

        Livewire::test(DailyRevenuePage::class)->assertViewHas('summary', ['customers' => 1, 'revenue' => 1600000, 'commission' => 0])->assertViewHas('entries', fn ($entries): bool => $entries->total() === 16 && $entries->count() === 15);
    }

    public function test_package_customer_session_records_zero_revenue_and_rewards_fixed_commission_and_consumes_package_session(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $packageOffering = Offering::factory()->create([
            'type' => 'package',
            'price' => 1200000,
            'sessions_total' => 10,
            'status' => 'active',
        ]);

        CommissionRule::factory()->create([
            'offering_id' => $packageOffering->id,
            'beneficiary_type' => 'primary_technician',
            'calculation_type' => 'fixed',
            'value' => 50000,
            'is_active' => true,
        ]);

        $customerPackage = CustomerPackage::factory()->create([
            'customer_id' => $customer->id,
            'offering_id' => $packageOffering->id,
            'sessions_total' => 10,
            'sessions_used' => 2,
            'status' => 'active',
        ]);

        $row = [
            'key' => (string) Str::uuid(),
            'customer_type' => 'package',
            'customer_id' => (string) $customer->id,
            'name' => '',
            'phone' => '',
            'method' => 'cash',
            'reference' => '',
            'services' => [
                [
                    'key' => (string) Str::uuid(),
                    'offering_id' => (string) $packageOffering->id,
                    'employee_id' => (string) $employee->id,
                    'amount' => '0',
                ],
            ],
        ];

        Livewire::test(DailyRevenuePage::class)
            ->set('customers', [$row])
            ->call('save')
            ->assertHasNoErrors();

        // 1 invoice created with 0 VND collected
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'customer_package_id' => $customerPackage->id,
            'subtotal' => 0,
            'total_received' => 0,
            'status' => 'paid',
        ]);

        // Commission entry of 50.000 VND created for the employee
        $this->assertDatabaseHas('commission_entries', [
            'employee_id' => $employee->id,
            'offering_id' => $packageOffering->id,
            'calculation_type' => 'fixed',
            'amount' => 50000,
            'status' => 'pending',
        ]);

        // Customer package session was consumed (from 2 to 3)
        $this->assertEquals(3, $customerPackage->fresh()->sessions_used);
    }

    /** @return array<string, mixed> */
    private function customerRow(Customer $customer, Offering $offering, Employee $employee, string $amount): array
    {
        return ['key' => (string) Str::uuid(), 'customer_id' => (string) $customer->id, 'name' => '', 'phone' => '', 'method' => 'cash', 'reference' => '', 'services' => [$this->serviceRow($offering, $employee, $amount)]];
    }

    /** @return array<string, mixed> */
    private function newCustomerRow(Offering $offering, Employee $employee, string $name, string $phone): array
    {
        return ['key' => (string) Str::uuid(), 'customer_id' => 'new', 'name' => $name, 'phone' => $phone, 'method' => 'cash', 'reference' => '', 'services' => [$this->serviceRow($offering, $employee, '100000')]];
    }

    /** @return array{key: string, offering_id: string, employee_id: string, amount: string} */
    private function serviceRow(Offering $offering, Employee $employee, string $amount): array
    {
        return ['key' => (string) Str::uuid(), 'offering_id' => (string) $offering->id, 'employee_id' => (string) $employee->id, 'amount' => $amount];
    }
}
