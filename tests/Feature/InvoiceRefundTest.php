<?php

namespace Tests\Feature;

use App\Livewire\Admin\AppointmentsPage;
use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceRefund;
use App\Models\Offering;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PackageUsageService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_payment_rejects_an_invoice_linked_to_an_unfinished_appointment(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service', 'price' => 450000]);
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'status' => 'scheduled', 'payment_type' => 'service']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'appointment_id' => $appointment->id, 'subtotal' => 450000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'unit_price' => 450000, 'line_total' => 450000]);

        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->pay($invoice, 'cash');
    }

    public function test_payment_rejects_an_invoice_without_line_items(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'pending']);

        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->pay($invoice, 'cash');
    }

    public function test_package_appointment_requires_a_package_for_the_selected_offering(): void
    {
        $customer = Customer::factory()->create();
        $service = Offering::factory()->create(['type' => 'service']);
        $packageOffering = Offering::factory()->create(['type' => 'package', 'sessions_total' => 12]);
        $package = CustomerPackage::factory()->create(['customer_id' => $customer->id, 'offering_id' => $packageOffering->id]);

        Livewire::test(AppointmentsPage::class)
            ->set('customerId', (string) $customer->id)
            ->set('offeringId', (string) $service->id)
            ->set('paymentType', 'package')
            ->set('packageId', (string) $package->id)
            ->set('appointmentDate', today()->toDateString())
            ->set('startTime', '08:30')
            ->set('endTime', '09:30')
            ->call('saveAppointment')
            ->assertHasErrors(['packageId']);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_package_usage_rejects_an_appointment_linked_to_a_different_package(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $offering = Offering::factory()->create(['type' => 'package']);
        $package = CustomerPackage::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id]);
        $otherPackage = CustomerPackage::factory()->create(['customer_id' => $otherCustomer->id, 'offering_id' => $offering->id]);
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'offering_id' => $offering->id,
            'customer_package_id' => $otherPackage->id,
            'status' => 'completed_pending_payment',
            'payment_type' => 'package',
        ]);

        $this->expectException(ValidationException::class);
        app(PackageUsageService::class)->consume($package, $appointment, null, 'Xác nhận trừ buổi');
    }

    public function test_package_usage_rejects_an_appointment_for_a_different_offering(): void
    {
        $customer = Customer::factory()->create();
        $packageOffering = Offering::factory()->create(['type' => 'package']);
        $otherOffering = Offering::factory()->create(['type' => 'package']);
        $package = CustomerPackage::factory()->create(['customer_id' => $customer->id, 'offering_id' => $packageOffering->id]);
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'offering_id' => $otherOffering->id,
            'customer_package_id' => $package->id,
            'status' => 'completed_pending_payment',
            'payment_type' => 'package',
        ]);

        $this->expectException(ValidationException::class);
        app(PackageUsageService::class)->consume($package, $appointment, null, 'Xác nhận trừ buổi');
    }

    public function test_package_usage_cannot_be_recorded_twice_for_the_same_appointment(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create(['type' => 'package']);
        $package = CustomerPackage::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id]);
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'offering_id' => $offering->id,
            'customer_package_id' => $package->id,
            'status' => 'completed_pending_payment',
            'payment_type' => 'package',
        ]);
        app(PackageUsageService::class)->consume($package, $appointment, null, 'Xác nhận trừ buổi');

        $this->expectException(ValidationException::class);
        app(PackageUsageService::class)->consume($package->fresh(), $appointment->fresh(), null, 'Xác nhận trừ buổi lần hai');
    }

    public function test_package_completion_rolls_back_when_the_package_no_longer_matches_the_appointment(): void
    {
        $customer = Customer::factory()->create();
        $packageOffering = Offering::factory()->create(['type' => 'package']);
        $otherOffering = Offering::factory()->create(['type' => 'package']);
        $package = CustomerPackage::factory()->create(['customer_id' => $customer->id, 'offering_id' => $packageOffering->id]);
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'offering_id' => $otherOffering->id,
            'customer_package_id' => $package->id,
            'status' => 'in_service',
            'payment_type' => 'package',
        ]);

        Livewire::test(AppointmentsPage::class)
            ->call('completeService', $appointment->id)
            ->assertHasErrors(['package']);

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'in_service']);
        $this->assertSame(0, (int) $package->fresh()->sessions_used);
        $this->assertDatabaseCount('package_usages', 0);
    }

    public function test_full_service_refund_reverses_pending_commission_and_preserves_audit_trail(): void
    {
        $customer = Customer::factory()->create(['total_spend' => 0]);
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service', 'price' => 450000]);
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'primary_employee_id' => $employee->id, 'status' => 'completed_pending_payment', 'payment_type' => 'service']);
        $rule = CommissionRule::factory()->create(['offering_id' => $offering->id, 'value' => 10, 'starts_on' => today()->subDay()]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'appointment_id' => $appointment->id, 'subtotal' => 450000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'employee_id' => $employee->id, 'unit_price' => 450000, 'line_total' => 450000]);
        app(CheckoutService::class)->pay($invoice, 'cash');

        $refund = app(RefundService::class)->refund($invoice->fresh(), 'cash', 'Khách yêu cầu hoàn tiền');

        $this->assertInstanceOf(InvoiceRefund::class, $refund);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('invoice_refunds', ['invoice_id' => $invoice->id, 'amount' => 450000]);
        $this->assertDatabaseHas('commission_entries', ['invoice_id' => $invoice->id, 'commission_rule_id' => $rule->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'refunded']);
        $this->assertSame(0, (int) $customer->fresh()->total_spend);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.refunded', 'subject_id' => $invoice->id]);
    }

    public function test_full_package_refund_is_rejected_after_a_session_has_been_used(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create(['type' => 'package', 'sessions_total' => 12, 'price' => 12000000]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'type' => 'package', 'subtotal' => 12000000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'unit_price' => 12000000, 'line_total' => 12000000, 'package_sessions' => 12]);
        app(CheckoutService::class)->pay($invoice, 'cash');
        $package = CustomerPackage::query()->where('customer_id', $customer->id)->where('offering_id', $offering->id)->firstOrFail();
        app(PackageUsageService::class)->consume($package, null, null, 'Đã dùng buổi đầu');

        $this->expectException(ValidationException::class);
        app(RefundService::class)->refund($invoice->fresh(), 'cash', 'Khách yêu cầu hoàn gói');
    }

    public function test_full_refund_is_rejected_when_commission_period_is_locked(): void
    {
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service', 'price' => 450000]);
        $rule = CommissionRule::factory()->create(['offering_id' => $offering->id, 'starts_on' => today()->subDay()]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'subtotal' => 450000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'employee_id' => $employee->id, 'unit_price' => 450000, 'line_total' => 450000]);
        app(CheckoutService::class)->pay($invoice, 'cash');
        CommissionEntry::query()->where('invoice_id', $invoice->id)->where('commission_rule_id', $rule->id)->update(['status' => 'locked']);

        $this->expectException(ValidationException::class);
        app(RefundService::class)->refund($invoice->fresh(), 'cash', 'Khách yêu cầu hoàn tiền');
    }
}
