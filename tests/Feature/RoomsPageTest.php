<?php

namespace Tests\Feature;

use App\Livewire\Admin\RoomsPage;
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

class RoomsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_rooms_page_renders_successfully(): void
    {
        $area = ResourceArea::create([
            'code' => 'FACIAL',
            'name' => 'Khu Chăm Sóc Da',
            'floor' => 'Tầng 1',
            'description' => 'Chăm sóc da chuyên sâu',
            'status' => 'active',
        ]);

        $resource = ServiceResource::create([
            'resource_area_id' => $area->id,
            'code' => 'D01',
            'name' => 'Giường Da 01',
            'resource_type' => 'bed',
            'status' => 'ready',
        ]);

        $this->get('/phong-giuong')
            ->assertOk()
            ->assertSee('Sơ đồ phòng & giường vận hành')
            ->assertSee('Khu Chăm Sóc Da')
            ->assertSee('Giường Da 01')
            ->assertSee('Trống sạch sẵn sàng');
    }

    public function test_rooms_page_shows_active_appointment_details_for_busy_bed(): void
    {
        $area = ResourceArea::create([
            'code' => 'BODY',
            'name' => 'Khu Massage Body',
            'floor' => 'Tầng 2',
            'description' => 'Trị liệu toàn thân',
            'status' => 'active',
        ]);

        $resource = ServiceResource::create([
            'resource_area_id' => $area->id,
            'code' => 'B01',
            'name' => 'Giường Body 01',
            'resource_type' => 'bed',
            'status' => 'busy',
        ]);

        $customer = Customer::factory()->create(['name' => 'Nguyễn Thị Hoa', 'phone' => '0912345678']);
        $offering = Offering::factory()->create(['name' => 'Massage Đá Nóng 90P']);
        $employee = Employee::factory()->create(['name' => 'Trần Thu Trang']);

        Appointment::factory()->create([
            'customer_id' => $customer->id,
            'offering_id' => $offering->id,
            'primary_employee_id' => $employee->id,
            'service_resource_id' => $resource->id,
            'status' => 'in_service',
            'appointment_date' => today(),
            'start_time' => '14:00',
            'end_time' => '15:30',
        ]);

        Livewire::test(RoomsPage::class)
            ->assertSee('Nguyễn Thị Hoa')
            ->assertSee('0912345678')
            ->assertSee('Massage Đá Nóng 90P')
            ->assertSee('Trần Thu Trang')
            ->assertSee('14:00 - 15:30');
    }

    public function test_admin_can_mark_cleaning_resource_as_ready(): void
    {
        $area = ResourceArea::create([
            'code' => 'WASH',
            'name' => 'Khu Gội Đầu',
            'floor' => 'Tầng 3',
            'description' => 'Dưỡng sinh',
            'status' => 'active',
        ]);

        $resource = ServiceResource::create([
            'resource_area_id' => $area->id,
            'code' => 'W01',
            'name' => 'Bồn Gội 01',
            'resource_type' => 'bed',
            'status' => 'cleaning',
        ]);

        Livewire::test(RoomsPage::class)
            ->call('setReady', $resource->id)
            ->assertSee('sẵn sàng đón khách');

        $this->assertEquals('ready', $resource->fresh()->status);
    }

    public function test_admin_can_filter_by_status_and_area(): void
    {
        $area1 = ResourceArea::create(['code' => 'A1', 'name' => 'Khu A', 'floor' => 'Tầng 1', 'description' => 'A', 'status' => 'active']);
        $area2 = ResourceArea::create(['code' => 'A2', 'name' => 'Khu B', 'floor' => 'Tầng 2', 'description' => 'B', 'status' => 'active']);

        $res1 = ServiceResource::create(['resource_area_id' => $area1->id, 'code' => 'G1', 'name' => 'Giường 101', 'resource_type' => 'bed', 'status' => 'ready']);
        $res2 = ServiceResource::create(['resource_area_id' => $area2->id, 'code' => 'G2', 'name' => 'Giường 202', 'resource_type' => 'bed', 'status' => 'cleaning']);

        Livewire::test(RoomsPage::class)
            ->set('statusFilter', 'cleaning')
            ->assertSee('Giường 202')
            ->assertDontSee('Giường 101')
            ->set('statusFilter', 'all')
            ->set('areaFilter', (string) $area1->id)
            ->assertSee('Giường 101')
            ->assertDontSee('Giường 202');
    }

    public function test_admin_can_open_and_save_maintenance(): void
    {
        $area = ResourceArea::create(['code' => 'A', 'name' => 'Khu Chung', 'floor' => 'Tầng 1', 'description' => 'D', 'status' => 'active']);
        $resource = ServiceResource::create(['resource_area_id' => $area->id, 'code' => 'G01', 'name' => 'Giường VIP 01', 'resource_type' => 'bed', 'status' => 'ready']);

        Livewire::test(RoomsPage::class)
            ->call('openMaintenance', $resource->id)
            ->assertSet('showMaintenance', true)
            ->assertSet('maintenanceId', $resource->id)
            ->set('maintenanceNote', 'Bảo dưỡng định kỳ hệ thống sưởi')
            ->set('maintenanceUntil', '2026-09-20T17:00')
            ->call('saveMaintenance')
            ->assertSet('showMaintenance', false)
            ->assertSee('Đã chuyển');

        $this->assertEquals('maintenance', $resource->fresh()->status);
        $this->assertEquals('Bảo dưỡng định kỳ hệ thống sưởi', $resource->fresh()->maintenance_note);
    }
}
