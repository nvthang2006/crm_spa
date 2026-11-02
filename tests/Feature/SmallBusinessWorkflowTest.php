<?php

namespace Tests\Feature;

use App\Livewire\Admin\AppointmentsPage;
use App\Livewire\Admin\DashboardPage;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Offering;
use App\Models\ServiceResource;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\OperationalAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SmallBusinessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_counts_paid_customers_once_and_hides_room_controls(): void
    {
        $this->assertFalse(config('spa.resource_management_enabled'));
        $customer = Customer::factory()->create();
        Invoice::factory()->count(2)->create(['customer_id' => $customer->id, 'status' => 'paid', 'paid_at' => now(), 'total_received' => 100000]);
        Invoice::factory()->create(['status' => 'pending']);
        Invoice::factory()->create(['status' => 'refunded', 'paid_at' => now()]);
        Invoice::factory()->create(['status' => 'paid', 'paid_at' => today()->subDay()]);

        Livewire::test(DashboardPage::class)
            ->assertViewHas('customersToday', 1)
            ->assertViewHas('revenueToday', 200000)
            ->assertSee('Khách đã thanh toán')
            ->assertDontSee('Trạng thái phòng nhanh')
            ->assertDontSee('Chưa phân phòng');
        $this->get('/dashboard')->assertOk()->assertDontSee('Phòng &amp; giường', false);
    }

    public function test_appointment_form_ignores_hidden_resource_input_and_legacy_query_link(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create(['type' => 'service']);
        $resource = ServiceResource::factory()->create();
        $this->get('/lich-hen?resource_id='.$resource->id)
            ->assertOk()->assertDontSee('Phòng / giường')->assertDontSee('Tạo lịch hẹn & xếp ca phục vụ');

        Livewire::test(AppointmentsPage::class)->call('newAppointment')
            ->assertDontSee('Phòng / giường thực hiện')
            ->set('customerId', (string) $customer->id)->set('offeringId', (string) $offering->id)
            ->set('resourceId', '999999')->set('startTime', '10:00')->set('endTime', '11:00')
            ->call('saveAppointment')->assertHasNoErrors();
        $this->assertNull(Appointment::query()->sole()->service_resource_id);
    }

    public function test_edit_preserves_old_resource_relationship_without_showing_or_reassigning_it(): void
    {
        $resource = ServiceResource::factory()->create(['status' => 'maintenance']);
        $appointment = Appointment::factory()->create(['service_resource_id' => $resource->id]);
        Livewire::test(AppointmentsPage::class)->call('editAppointment', $appointment->id)
            ->set('resourceId', '')->set('notes', 'Khách cần thao tác nhẹ')
            ->call('saveAppointment')->assertHasNoErrors();
        $this->assertSame($resource->id, $appointment->fresh()->service_resource_id);
        $this->assertSame('maintenance', $resource->fresh()->status);
    }

    public function test_legacy_unready_bed_does_not_block_service_or_change_its_status(): void
    {
        $resource = ServiceResource::factory()->create(['status' => 'maintenance']);
        $appointment = Appointment::factory()->create(['service_resource_id' => $resource->id, 'status' => 'arrived']);
        Livewire::test(AppointmentsPage::class)->call('startService', $appointment->id)->assertHasNoErrors();
        $this->assertSame('in_service', $appointment->fresh()->status);
        $this->assertNotNull($appointment->fresh()->started_at);
        Livewire::test(AppointmentsPage::class)->call('completeService', $appointment->id)->assertHasNoErrors();
        $this->assertSame('completed_pending_payment', $appointment->fresh()->status);
        $this->assertSame('pending', $appointment->invoice()->sole()->status);
        Livewire::test(AppointmentsPage::class)->call('cancelAppointment', $appointment->id)->assertHasNoErrors();
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame('void', $appointment->invoice()->sole()->status);
        $this->assertSame('maintenance', $resource->fresh()->status);
    }

    public function test_same_legacy_room_with_different_employees_can_overlap(): void
    {
        $resource = ServiceResource::factory()->create();
        Appointment::factory()->create(['service_resource_id' => $resource->id]);
        $candidate = Appointment::factory()->make(['service_resource_id' => $resource->id]);
        $saved = app(AppointmentService::class)->save($candidate);
        $this->assertModelExists($saved);
    }

    public function test_employee_conflict_still_checks_support_employee(): void
    {
        $employee = Employee::factory()->create();
        Appointment::factory()->create(['support_employee_id' => $employee->id]);
        $candidate = Appointment::factory()->make(['primary_employee_id' => $employee->id]);
        $this->expectException(ValidationException::class);
        app(AppointmentService::class)->save($candidate);
    }

    public function test_old_room_alerts_resolve_without_deleting_history_and_payment_alert_remains(): void
    {
        ServiceResource::factory()->create(['status' => 'cleaning']);
        Invoice::factory()->create(['status' => 'pending']);
        $service = app(OperationalAlertService::class);
        config(['spa.resource_management_enabled' => true]);
        $service->sync();
        $this->assertDatabaseHas('operational_alerts', ['type' => 'resource_cleaning', 'is_active' => true]);
        config(['spa.resource_management_enabled' => false]);
        $alerts = $service->forUser(auth()->user());
        $this->assertSame(['invoice_pending'], $alerts->pluck('type')->all());
        $this->assertDatabaseHas('operational_alerts', ['type' => 'resource_cleaning', 'is_active' => false]);
        $this->assertDatabaseCount('operational_alerts', 2);
    }

    public function test_timeline_groups_by_employee_instead_of_legacy_bed(): void
    {
        $employee = Employee::factory()->create(['name' => 'Nhân viên Tuyết']);
        $resource = ServiceResource::factory()->create(['name' => 'Giường lịch sử']);
        Appointment::factory()->create(['primary_employee_id' => $employee->id, 'service_resource_id' => $resource->id, 'start_time' => '10:00']);
        Livewire::test(AppointmentsPage::class)->assertSee('timeline-employee-'.$employee->id, false)
            ->assertSee('Nhân viên Tuyết')->assertDontSee('Giường lịch sử')->assertDontSee('Chưa xếp phòng');
    }

    public function test_enabled_resource_management_retains_bed_conflict_and_readiness_check(): void
    {
        config(['spa.resource_management_enabled' => true]);
        $resource = ServiceResource::factory()->create(['status' => 'maintenance']);
        $appointment = Appointment::factory()->create(['service_resource_id' => $resource->id, 'status' => 'arrived']);
        $candidate = Appointment::factory()->make(['service_resource_id' => $resource->id]);
        try {
            app(AppointmentService::class)->save($candidate);
            $this->fail('Phải chặn lịch trùng giường khi bật quản lý tài nguyên.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('appointment', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        app(AppointmentService::class)->transition($appointment, 'in_service');
    }
}
