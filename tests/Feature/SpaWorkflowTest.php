<?php

namespace Tests\Feature;

use App\Livewire\Admin\CustomersPage;
use App\Models\Appointment;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offering;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\AuditService;
use App\Services\CheckoutService;
use App\Services\PackageUsageService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SpaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_authenticated_admin_can_open_all_stitch_screens(): void
    {
        foreach (['/dashboard', '/nhan-vien', '/bang-luong', '/dich-vu', '/lich-hen', '/phong-giuong', '/khach-hang', '/hoa-don'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_overlapping_employee_is_blocked_without_override_reason(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create();
        $employee = Employee::factory()->create();
        $existing = Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'primary_employee_id' => $employee->id, 'appointment_date' => '2026-09-04', 'start_time' => '10:00', 'end_time' => '11:00']);
        $candidate = Appointment::factory()->make(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'primary_employee_id' => $employee->id, 'appointment_date' => $existing->appointment_date, 'start_time' => '10:30', 'end_time' => '11:30']);

        $this->expectException(ValidationException::class);
        app(AppointmentService::class)->save($candidate);
    }

    public function test_override_reason_allows_overlap_and_is_audited(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create();
        $employee = Employee::factory()->create();
        Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'primary_employee_id' => $employee->id, 'appointment_date' => '2026-09-04', 'start_time' => '10:00', 'end_time' => '11:00']);
        $candidate = Appointment::factory()->make(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'primary_employee_id' => $employee->id, 'appointment_date' => '2026-09-04', 'start_time' => '10:30', 'end_time' => '11:30']);

        $saved = app(AppointmentService::class)->save($candidate, 'Khách VIP đồng ý dùng chung KTV trong 30 phút');

        $this->assertSame('Khách VIP đồng ý dùng chung KTV trong 30 phút', $saved->override_conflict_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.saved', 'reason' => 'Khách VIP đồng ý dùng chung KTV trong 30 phút']);
    }

    public function test_package_usage_can_be_restored_once(): void
    {
        $package = CustomerPackage::factory()->create(['sessions_total' => 2, 'sessions_used' => 0]);
        app(PackageUsageService::class)->consume($package, null, null, 'Khách xác nhận tại quầy');
        $this->assertSame(1, $package->fresh()->sessions_used);
        $this->assertDatabaseHas('package_usages', ['customer_package_id' => $package->id, 'session_number' => 1, 'direction' => 'use', 'verification_method' => 'admin_confirmation']);
        app(PackageUsageService::class)->restore($package, 1, 'Hủy buổi do khách đổi lịch');
        $this->assertSame(0, $package->fresh()->sessions_used);

        $this->expectException(ValidationException::class);
        app(PackageUsageService::class)->restore($package, 1, 'Hoàn lại lần hai');
    }

    public function test_cancelling_a_completed_service_voids_its_unpaid_invoice(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'offering_id' => $offering->id,
            'status' => 'completed_pending_payment',
            'payment_type' => 'service',
        ]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'status' => 'pending',
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id]);

        app(AppointmentService::class)->transition($appointment, 'cancelled', 'Khách không tiếp tục dịch vụ');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'void']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.voided', 'subject_id' => $invoice->id]);
    }

    public function test_customer_package_actions_are_scoped_to_the_open_customer_profile(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $package = CustomerPackage::factory()->create(['customer_id' => $customer->id]);

        Livewire::test(CustomersPage::class)
            ->set('selectedId', $otherCustomer->id)
            ->call('consumeSession', $package->id)
            ->assertHasErrors(['package']);

        $this->assertSame(0, (int) $package->fresh()->sessions_used);
    }

    public function test_paid_package_invoice_creates_customer_card_and_commission_entry(): void
    {
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $packageOffering = Offering::factory()->create(['type' => 'package', 'sessions_total' => 12, 'price' => 12000000]);
        $rule = CommissionRule::factory()->create(['offering_id' => $packageOffering->id, 'value' => 5, 'starts_on' => today()->subDay()]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'type' => 'package', 'subtotal' => 12000000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $packageOffering->id, 'employee_id' => $employee->id, 'unit_price' => 12000000, 'line_total' => 12000000, 'package_sessions' => 12]);
        app(CheckoutService::class)->pay($invoice, 'vietqr', 'QR-001');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'payment_method' => 'vietqr']);
        $this->assertDatabaseHas('customer_packages', ['customer_id' => $customer->id, 'offering_id' => $packageOffering->id, 'sessions_total' => 12]);
        $this->assertDatabaseHas('commission_entries', ['invoice_id' => $invoice->id, 'employee_id' => $employee->id, 'commission_rule_id' => $rule->id, 'amount' => 600000]);
    }

    public function test_non_cash_payment_requires_a_transaction_reference(): void
    {
        $invoice = Invoice::factory()->create(['subtotal' => 450000, 'discount_amount' => 0]);

        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->pay($invoice, 'pos');
    }

    public function test_health_profile_is_encrypted_and_view_access_is_audited(): void
    {
        $customer = Customer::factory()->create();
        $customer->healthProfile()->create(['allergy_notes' => 'Dị ứng hương liệu', 'current_condition' => 'Da nhạy cảm']);
        $raw = DB::table('customer_health_profiles')->where('customer_id', $customer->id)->value('allergy_notes');
        $this->assertNotSame('Dị ứng hương liệu', $raw);
        $customer->refresh()->load('healthProfile');
        $this->assertSame('Dị ứng hương liệu', $customer->healthProfile->allergy_notes);
        app(AuditService::class)->record('customer.health_viewed', $customer);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.health_viewed', 'subject_id' => $customer->id]);
    }

    public function test_payroll_requires_reviewed_adjustments_before_locking(): void
    {
        $employee = Employee::factory()->create(['base_salary' => 7000000]);
        $period = app(PayrollService::class)->generate('2026-09-01', '2026-09-30');
        $adjustment = app(PayrollService::class)->createAdjustment($period, $employee->id, 'bonus', 250000, 'Thưởng đạt chỉ tiêu tháng');
        $this->expectException(ValidationException::class);
        app(PayrollService::class)->lock($period);
        $this->assertSame('pending', $adjustment->fresh()->status);
    }

    public function test_generate_reuses_period_when_dates_are_stored_with_time_component(): void
    {
        $existing = PayrollPeriod::factory()->create([
            'code' => 'BL-20260901',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => 'draft',
        ]);

        $period = app(PayrollService::class)->generate('2026-09-01', '2026-09-30');

        $this->assertSame($existing->id, $period->id);
        $this->assertSame(1, PayrollPeriod::query()->count());
    }

    public function test_approved_payroll_adjustment_updates_net_pay_and_can_be_locked(): void
    {
        $employee = Employee::factory()->create(['base_salary' => 7000000]);
        $period = app(PayrollService::class)->generate('2026-09-01', '2026-09-30');
        $adjustment = app(PayrollService::class)->createAdjustment($period, $employee->id, 'bonus', 250000, 'Thưởng đạt chỉ tiêu tháng');
        app(PayrollService::class)->reviewAdjustment($adjustment, true);
        app(PayrollService::class)->lock($period->fresh());

        $this->assertSame('locked', $period->fresh()->status);
        $this->assertSame(7250000, (int) $period->fresh()->lines()->where('employee_id', $employee->id)->value('net_pay'));
        $this->assertSame('approved', $adjustment->fresh()->status);
    }
}
