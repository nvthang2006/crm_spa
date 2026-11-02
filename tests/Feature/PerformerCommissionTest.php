<?php

namespace Tests\Feature;

use App\Livewire\Admin\DailyRevenuePage;
use App\Livewire\Admin\ServicesPage;
use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offering;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PerformerCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_rates_replace_the_general_rate_only_for_the_matching_performer_and_match_payroll(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $tuyet = Employee::factory()->create(['name' => 'Tuyết']);
        $thuy = Employee::factory()->create(['name' => 'Thúy']);
        $other = Employee::factory()->create();
        $recipient = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'value' => 5]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'performer_employee', 'beneficiary_employee_id' => $tuyet->id, 'value' => 10]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'performer_employee', 'beneficiary_employee_id' => $thuy->id, 'value' => 15]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'specific_employee', 'beneficiary_employee_id' => $recipient->id, 'calculation_type' => 'fixed', 'value' => 1000]);
        $row = ['key' => (string) Str::uuid(), 'customer_id' => (string) $customer->id, 'name' => '', 'phone' => '', 'method' => 'cash', 'reference' => '', 'services' => []];
        foreach ([$tuyet, $thuy, $other] as $employee) {
            $row['services'][] = ['key' => (string) Str::uuid(), 'offering_id' => (string) $offering->id, 'employee_id' => (string) $employee->id, 'amount' => '100000'];
        }

        Livewire::test(DailyRevenuePage::class)->set('customers', [$row])
            ->assertViewHas('draft', ['customers' => 1, 'revenue' => 300000, 'commission' => 33000])
            ->call('save')->assertHasNoErrors()
            ->assertViewHas('summary', ['customers' => 1, 'revenue' => 300000, 'commission' => 33000]);

        $this->assertDatabaseCount('commission_entries', 6);
        $this->assertDatabaseHas('commission_entries', ['employee_id' => $tuyet->id, 'rate' => 10, 'amount' => 10000]);
        $this->assertDatabaseHas('commission_entries', ['employee_id' => $thuy->id, 'rate' => 15, 'amount' => 15000]);
        $this->assertDatabaseHas('commission_entries', ['employee_id' => $other->id, 'rate' => 5, 'amount' => 5000]);
        $payroll = app(PayrollService::class)->generate('2026-10-01', '2026-10-31');
        $this->assertSame(33000, (int) $payroll->total_commission);
        $this->assertSame(15000, (int) $payroll->lines()->where('employee_id', $thuy->id)->first()->commission_amount);
    }

    #[TestWith(['fixed', 12000, '2026-10-01', null, true, 12000])]
    #[TestWith(['percentage', 15, '2026-10-01', '2026-10-05', true, 15000])]
    #[TestWith(['percentage', 15, '2026-10-06', null, true, 5000])]
    #[TestWith(['percentage', 15, '2026-09-01', '2026-10-04', true, 5000])]
    #[TestWith(['percentage', 15, '2026-10-01', null, false, 5000])]
    public function test_personal_rule_respects_dates_status_and_calculation_type(string $type, int $value, string $starts, ?string $ends, bool $active, int $expected): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'value' => 5, 'starts_on' => '2026-09-01']);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'performer_employee', 'beneficiary_employee_id' => $employee->id, 'calculation_type' => $type, 'value' => $value, 'starts_on' => $starts, 'ends_on' => $ends, 'is_active' => $active]);
        $row = ['key' => (string) Str::uuid(), 'customer_id' => (string) $customer->id, 'name' => '', 'phone' => '', 'method' => 'cash', 'reference' => '', 'services' => [['key' => (string) Str::uuid(), 'offering_id' => (string) $offering->id, 'employee_id' => (string) $employee->id, 'amount' => '100000']]];

        Livewire::test(DailyRevenuePage::class)->set('customers', [$row])
            ->assertViewHas('draft', ['customers' => 1, 'revenue' => 100000, 'commission' => $expected])
            ->call('save')->assertHasNoErrors();

        $this->assertSame($expected, (int) CommissionEntry::query()->sum('amount'));
        $this->assertDatabaseCount('commission_entries', 1);
    }

    public function test_checkout_from_an_appointment_uses_the_primary_employees_personal_rate_and_preserves_support_commission(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $support = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'primary_employee_id' => $employee->id, 'support_employee_id' => $support->id, 'status' => 'completed_pending_payment']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'appointment_id' => $appointment->id, 'subtotal' => 100000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'employee_id' => $employee->id, 'quantity' => 1, 'line_total' => 100000]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'value' => 5]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'performer_employee', 'beneficiary_employee_id' => $employee->id, 'value' => 15]);
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'support_technician', 'calculation_type' => 'fixed', 'value' => 2000]);

        app(CheckoutService::class)->pay($invoice, 'cash');

        $this->assertDatabaseHas('commission_entries', ['invoice_id' => $invoice->id, 'employee_id' => $employee->id, 'rate' => 15, 'amount' => 15000]);
        $this->assertDatabaseHas('commission_entries', ['invoice_id' => $invoice->id, 'employee_id' => $support->id, 'amount' => 2000]);
        $this->assertDatabaseCount('commission_entries', 2);
    }

    public function test_settings_save_and_edit_a_personal_rule_and_reject_an_overlapping_rule(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create();
        $component = Livewire::test(ServicesPage::class)->call('newCommission', $offering->id)
            ->set('beneficiaryType', 'performer_employee')->set('beneficiaryEmployeeId', (string) $employee->id)
            ->set('commissionValue', '15')->call('saveCommission')->assertHasNoErrors();
        $rule = CommissionRule::query()->sole();
        $this->assertSame('performer_employee', $rule->beneficiary_type);
        $this->assertSame($employee->id, $rule->beneficiary_employee_id);
        $component->call('editCommission', $rule->id)->set('commissionValue', '20')->call('saveCommission')->assertHasNoErrors();
        $this->assertDatabaseHas('commission_rules', ['id' => $rule->id, 'value' => 20]);

        $component->call('newCommission', $offering->id)->set('beneficiaryType', 'performer_employee')
            ->set('beneficiaryEmployeeId', (string) $employee->id)->call('saveCommission')->assertHasErrors('commission');

        $this->assertDatabaseCount('commission_rules', 1);
    }

    public function test_adjacent_personal_rules_can_be_saved_without_changing_previous_commission_snapshots(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create();
        $rule = CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'performer_employee', 'beneficiary_employee_id' => $employee->id, 'value' => 10, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
        $entry = CommissionEntry::factory()->create(['employee_id' => $employee->id, 'commission_rule_id' => $rule->id, 'offering_id' => $offering->id, 'rate' => 10, 'amount' => 10000, 'occurred_at' => '2026-09-02']);

        Livewire::test(ServicesPage::class)->call('newCommission', $offering->id)->set('beneficiaryType', 'performer_employee')
            ->set('beneficiaryEmployeeId', (string) $employee->id)->set('startsOn', '2026-10-01')->set('commissionValue', '15')
            ->call('saveCommission')->assertHasNoErrors();

        $this->assertDatabaseCount('commission_rules', 2);
        $this->assertDatabaseHas('commission_entries', ['id' => $entry->id, 'rate' => 10, 'amount' => 10000]);
    }

    public function test_personal_rule_requires_an_active_employee_and_activation_cannot_overlap(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $inactive = Employee::factory()->create(['status' => 'inactive']);
        $offering = Offering::factory()->create();
        $component = Livewire::test(ServicesPage::class)->call('newCommission', $offering->id)->set('beneficiaryType', 'performer_employee');
        $component->call('saveCommission')->assertHasErrors('beneficiaryEmployeeId')
            ->set('beneficiaryEmployeeId', (string) $inactive->id)->call('saveCommission')->assertHasErrors('beneficiaryEmployeeId');
        CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'performer_employee', 'beneficiary_employee_id' => $employee->id]);
        $rule = CommissionRule::factory()->create(['offering_id' => $offering->id, 'beneficiary_type' => 'performer_employee', 'beneficiary_employee_id' => $employee->id, 'is_active' => false]);

        $component->call('toggleCommission', $rule->id)->assertHasErrors('commission');

        $this->assertFalse($rule->fresh()->is_active);
    }
}
