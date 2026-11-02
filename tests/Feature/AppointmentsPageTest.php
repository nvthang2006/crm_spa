<?php

namespace Tests\Feature;

use App\Livewire\Admin\AppointmentsPage;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Offering;
use App\Models\ResourceArea;
use App\Models\ServiceResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentsPageTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Offering $offering;

    protected Employee $employee;

    protected ServiceResource $resource;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spa.resource_management_enabled' => true]);
        $this->actingAs(User::factory()->create());

        $this->customer = Customer::create([
            'code' => 'KH001',
            'name' => 'Nguyễn Thị Hoa',
            'phone' => '0912345678',
            'status' => 'active',
        ]);

        $this->offering = Offering::create([
            'code' => 'FACIAL-BASIC',
            'type' => 'service',
            'name' => 'Chăm sóc da cơ bản',
            'duration_minutes' => 60,
            'price' => 350000,
            'status' => 'active',
        ]);

        $this->employee = Employee::create([
            'code' => 'NV001',
            'name' => 'Trần Thị Mai',
            'job_title' => 'Kỹ thuật viên chính',
            'employment_status' => 'active',
            'phone' => '0987654321',
        ]);

        $area = ResourceArea::create([
            'code' => 'FACIAL',
            'name' => 'Khu Chăm Sóc Da',
            'floor' => 'Tầng 1',
            'status' => 'active',
        ]);

        $this->resource = ServiceResource::create([
            'resource_area_id' => $area->id,
            'code' => 'D01',
            'name' => 'Giường Da 01',
            'resource_type' => 'bed',
            'status' => 'ready',
        ]);
    }

    public function test_appointments_page_renders_successfully(): void
    {
        $this->get('/lich-hen')
            ->assertOk()
            ->assertSee('Lịch hẹn & tiếp đón')
            ->assertSee('Tạo lịch hẹn');
    }

    public function test_modal_is_hidden_by_default(): void
    {
        Livewire::test(AppointmentsPage::class)
            ->assertSet('showForm', false)
            ->assertDontSee('Tạo lịch hẹn & xếp ca phục vụ');
    }

    public function test_clicking_create_appointment_opens_modal(): void
    {
        Livewire::test(AppointmentsPage::class)
            ->call('newAppointment')
            ->assertSet('showForm', true)
            ->assertSee('Tạo lịch hẹn & xếp ca phục vụ')
            ->assertSee('Xác nhận tạo lịch hẹn')
            ->assertSee('Khách hàng')
            ->assertSee('Nguyễn Thị Hoa');
    }

    public function test_clicking_cancel_closes_modal(): void
    {
        Livewire::test(AppointmentsPage::class)
            ->call('newAppointment')
            ->assertSet('showForm', true)
            ->set('showForm', false)
            ->assertSet('showForm', false)
            ->assertDontSee('Tạo lịch hẹn & xếp ca phục vụ');
    }

    public function test_editing_appointment_opens_modal_with_data(): void
    {
        $appointment = Appointment::create([
            'code' => 'APPT-TEST-01',
            'customer_id' => $this->customer->id,
            'offering_id' => $this->offering->id,
            'primary_employee_id' => $this->employee->id,
            'service_resource_id' => $this->resource->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'payment_type' => 'service',
            'status' => 'scheduled',
            'notes' => 'Khách đến lần đầu',
        ]);

        Livewire::test(AppointmentsPage::class)
            ->call('editAppointment', $appointment->id)
            ->assertSet('showForm', true)
            ->assertSet('editingId', $appointment->id)
            ->assertSet('customerId', (string) $this->customer->id)
            ->assertSet('offeringId', (string) $this->offering->id)
            ->assertSet('primaryEmployeeId', (string) $this->employee->id)
            ->assertSet('resourceId', (string) $this->resource->id)
            ->assertSet('notes', 'Khách đến lần đầu')
            ->assertSee('Cập nhật thông tin lịch hẹn')
            ->assertSee('Lưu cập nhật lịch hẹn');
    }

    public function test_saving_appointment_creates_record_and_closes_modal(): void
    {
        $date = now()->toDateString();

        Livewire::test(AppointmentsPage::class)
            ->call('newAppointment')
            ->set('customerId', $this->customer->id)
            ->set('offeringId', $this->offering->id)
            ->set('appointmentDate', $date)
            ->set('startTime', '14:00')
            ->set('endTime', '15:00')
            ->set('primaryEmployeeId', $this->employee->id)
            ->set('resourceId', $this->resource->id)
            ->set('paymentType', 'service')
            ->call('saveAppointment')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('appointments', [
            'customer_id' => $this->customer->id,
            'offering_id' => $this->offering->id,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'status' => 'scheduled',
        ]);
    }

    public function test_resource_id_query_parameter_opens_modal_with_preselected_resource(): void
    {
        $this->get('/lich-hen?resource_id='.$this->resource->id)
            ->assertOk()
            ->assertSee('Tạo lịch hẹn & xếp ca phục vụ')
            ->assertSee($this->resource->name);
    }
}
