<?php

namespace Tests\Feature;

use App\Livewire\Admin\EmployeesPage;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
    }

    public function test_repeated_attendance_records_check_in_then_check_out_without_duplicates(): void
    {
        $employee = Employee::factory()->create();
        $page = Livewire::test(EmployeesPage::class)
            ->set('attendanceDate', '2026-10-12')
            ->call('recordAttendance', $employee->id)
            ->assertHasNoErrors();

        $attendance = AttendanceRecord::query()->sole();
        $this->assertTrue($attendance->check_in_at->equalTo(now()));
        $this->assertNull($attendance->check_out_at);
        $this->assertSame(auth()->id(), $attendance->recorded_by);

        $this->travel(8)->hours();
        $page->call('recordAttendance', $employee->id)->assertHasNoErrors();

        $this->assertDatabaseCount('attendance_records', 1);
        $attendance->refresh();
        $this->assertSame('09:00:00', $attendance->check_in_at->format('H:i:s'));
        $this->assertTrue($attendance->check_out_at->equalTo(now()));

        $this->travel(1)->hours();
        $page->call('recordAttendance', $employee->id)->assertHasNoErrors();
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertSame('17:00:00', $attendance->fresh()->check_out_at->format('H:i:s'));
    }

    public function test_existing_midnight_date_record_is_used_for_check_out(): void
    {
        $attendance = AttendanceRecord::factory()->create([
            'attendance_date' => '2026-10-12',
            'check_in_at' => now()->subHour(),
        ]);
        $this->assertSame('2026-10-12 00:00:00', $attendance->getRawOriginal('attendance_date'));

        Livewire::test(EmployeesPage::class)
            ->set('attendanceDate', '2026-10-12')
            ->call('recordAttendance', $attendance->employee_id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertTrue($attendance->fresh()->check_out_at->equalTo(now()));
    }

    public function test_legacy_date_only_record_is_reused_for_check_in(): void
    {
        $attendance = AttendanceRecord::factory()->create(['attendance_date' => '2026-10-12']);
        DB::table('attendance_records')->where('id', $attendance->id)
            ->update(['attendance_date' => '2026-10-12']);

        Livewire::test(EmployeesPage::class)
            ->set('attendanceDate', '2026-10-12')
            ->call('recordAttendance', $attendance->employee_id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertTrue($attendance->fresh()->check_in_at->equalTo(now()));
    }

    public function test_attendance_for_another_date_does_not_modify_existing_record(): void
    {
        $attendance = AttendanceRecord::factory()->create([
            'attendance_date' => '2026-10-11',
            'check_in_at' => now()->subDay(),
        ]);

        Livewire::test(EmployeesPage::class)
            ->set('attendanceDate', '2026-10-12')
            ->call('recordAttendance', $attendance->employee_id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('attendance_records', 2);
        $this->assertNull($attendance->fresh()->check_out_at);
        $this->assertSame(1, AttendanceRecord::query()
            ->where('employee_id', $attendance->employee_id)
            ->whereDate('attendance_date', '2026-10-12')->count());
    }

    public function test_invalid_attendance_date_does_not_create_a_record(): void
    {
        $employee = Employee::factory()->create();

        Livewire::test(EmployeesPage::class)
            ->set('attendanceDate', '2026-10-12 00:00:00')
            ->call('recordAttendance', $employee->id)
            ->assertHasErrors(['attendanceDate']);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_check_in_preserves_schedule_and_late_status_for_the_correct_employee(): void
    {
        $schedule = WorkSchedule::factory()->create([
            'work_date' => '2026-10-12',
            'starts_at' => '08:30',
        ]);
        $otherAttendance = AttendanceRecord::factory()->create([
            'attendance_date' => '2026-10-12',
            'check_in_at' => now()->subHour(),
        ]);

        Livewire::test(EmployeesPage::class)
            ->set('attendanceDate', '2026-10-12')
            ->call('recordAttendance', $schedule->employee_id)
            ->assertHasNoErrors();

        $attendance = AttendanceRecord::query()->where('employee_id', $schedule->employee_id)->sole();
        $this->assertSame($schedule->id, $attendance->work_schedule_id);
        $this->assertSame('late', $attendance->status);
        $this->assertSame(30, $attendance->late_minutes);
        $this->assertDatabaseCount('attendance_records', 2);
        $this->assertNull($otherAttendance->fresh()->check_out_at);
    }
}
