<?php

namespace App\Livewire\Admin;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\AuditService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeesPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'active';

    public string $attendanceDate = '';

    public bool $showForm = false;

    public bool $showScheduleForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $phone = '';

    public string $jobTitle = '';

    public string $shiftName = 'Ca sáng';

    public int $baseSalary = 0;

    public string $scheduleEmployeeId = '';

    public string $scheduleDate = '';

    public string $scheduleShiftName = 'Ca sáng';

    public string $scheduleStartsAt = '08:30';

    public string $scheduleEndsAt = '16:30';

    public string $scheduleNotes = '';

    public function mount(): void
    {
        $this->attendanceDate = today()->toDateString();
        $this->scheduleDate = today()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function newEmployee(): void
    {
        $this->reset(['editingId', 'code', 'name', 'phone', 'jobTitle', 'baseSalary']);
        $this->shiftName = 'Ca sáng';
        $this->showForm = true;
    }

    public function editEmployee(int $id): void
    {
        $employee = Employee::query()->findOrFail($id);
        $this->editingId = $employee->id;
        $this->code = $employee->code;
        $this->name = $employee->name;
        $this->phone = (string) $employee->phone;
        $this->jobTitle = $employee->job_title;
        $this->shiftName = (string) $employee->shift_name;
        $this->baseSalary = (int) $employee->base_salary;
        $this->showForm = true;
    }

    public function saveEmployee(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('employees', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'jobTitle' => ['required', 'string', 'max:120'],
            'shiftName' => ['required', 'string', 'max:60'],
            'baseSalary' => ['required', 'integer', 'min:0'],
        ], [], ['jobTitle' => 'chức vụ', 'shiftName' => 'ca làm việc', 'baseSalary' => 'lương cứng']);

        $employee = $this->editingId
            ? Employee::query()->findOrFail($this->editingId)
            : new Employee(['status' => 'active']);
        $employee->fill([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'job_title' => $validated['jobTitle'],
            'shift_name' => $validated['shiftName'],
            'base_salary' => $validated['baseSalary'],
        ]);
        $employee->save();
        session()->flash('success', $this->editingId ? 'Đã cập nhật nhân viên.' : 'Đã thêm nhân viên.');
        $this->showForm = false;
        $this->resetPage();
    }

    public function toggleStatus(int $id): void
    {
        $employee = Employee::query()->findOrFail($id);
        $employee->update(['status' => $employee->status === 'active' ? 'inactive' : 'active']);
        session()->flash('success', 'Đã cập nhật trạng thái nhân viên.');
    }

    public function recordAttendance(int $id): void
    {
        $validated = $this->validate(['attendanceDate' => ['required', 'date_format:Y-m-d']]);
        $attendanceDate = $validated['attendanceDate'];
        $employee = Employee::query()->with(['schedules' => fn ($query) => $query->whereDate('work_date', $attendanceDate)])->findOrFail($id);
        $attendance = AttendanceRecord::query()
            ->whereDate('attendance_date', $attendanceDate)
            ->firstOrCreate(
                ['employee_id' => $employee->id],
                ['attendance_date' => $attendanceDate, 'status' => 'present', 'recorded_by' => auth()->id()],
            );
        if ($attendance->check_in_at === null) {
            $checkIn = now();
            $schedule = $employee->schedules->first();
            $lateMinutes = $schedule?->starts_at
                ? max(0, $checkIn->diffInMinutes($attendanceDate.' '.$schedule->starts_at, false) * -1)
                : 0;
            $attendance->update([
                'work_schedule_id' => $schedule?->id,
                'check_in_at' => $checkIn,
                'status' => $lateMinutes > 0 ? 'late' : 'present',
                'late_minutes' => $lateMinutes,
                'recorded_by' => auth()->id(),
            ]);
        } elseif ($attendance->check_out_at === null) {
            $attendance->update(['check_out_at' => now(), 'recorded_by' => auth()->id()]);
        }
        session()->flash('success', 'Đã ghi nhận chấm công cho '.$employee->name.'.');
    }

    public function exportEmployees()
    {
        $this->validate(['attendanceDate' => ['required', 'date_format:Y-m-d']]);
        $employees = Employee::query()
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))
            ->with(['attendanceRecords' => fn ($query) => $query->whereDate('attendance_date', $this->attendanceDate)])
            ->orderBy('name')->get();

        return response()->streamDownload(function () use ($employees): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mã NV', 'Họ và tên', 'Chức vụ', 'Ca làm việc', 'Ngày chấm công', 'Check-in', 'Check-out', 'Trạng thái']);
            foreach ($employees as $employee) {
                $attendance = $employee->attendanceRecords->first();
                fputcsv($handle, [$employee->code, $employee->name, $employee->job_title, $employee->shift_name, $this->attendanceDate, $attendance?->check_in_at?->format('H:i:s'), $attendance?->check_out_at?->format('H:i:s'), $attendance?->status ?? 'absent']);
            }
            fclose($handle);
        }, 'nhan-vien-'.$this->attendanceDate.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function newSchedule(): void
    {
        $this->reset(['scheduleEmployeeId', 'scheduleNotes']);
        $this->scheduleDate = $this->attendanceDate;
        $this->scheduleShiftName = 'Ca sáng';
        $this->scheduleStartsAt = '08:30';
        $this->scheduleEndsAt = '16:30';
        $this->showScheduleForm = true;
    }

    public function saveSchedule(): void
    {
        $validated = $this->validate([
            'scheduleEmployeeId' => ['required', 'integer', 'exists:employees,id'],
            'scheduleDate' => ['required', 'date'],
            'scheduleShiftName' => ['required', 'string', 'max:60'],
            'scheduleStartsAt' => ['required', 'date_format:H:i'],
            'scheduleEndsAt' => ['required', 'date_format:H:i', 'after:scheduleStartsAt'],
            'scheduleNotes' => ['nullable', 'string', 'max:500'],
        ]);
        $schedule = WorkSchedule::query()->updateOrCreate(
            ['employee_id' => $validated['scheduleEmployeeId'], 'work_date' => $validated['scheduleDate']],
            ['shift_name' => $validated['scheduleShiftName'], 'starts_at' => $validated['scheduleStartsAt'], 'ends_at' => $validated['scheduleEndsAt'], 'status' => 'scheduled', 'notes' => $validated['scheduleNotes']],
        );
        app(AuditService::class)->record('employee.schedule_saved', $schedule, null, $schedule->fresh()->toArray());
        $this->showScheduleForm = false;
        session()->flash('success', 'Đã lưu ca làm việc.');
    }

    public function render()
    {
        $employees = Employee::query()
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))
            ->withCount(['primaryAppointments as today_services' => fn ($query) => $query->whereDate('appointment_date', today())->whereIn('status', ['in_service', 'completed_pending_payment', 'paid', 'package_used'])])
            ->with(['attendanceRecords' => fn ($query) => $query->whereDate('attendance_date', $this->attendanceDate), 'schedules' => fn ($query) => $query->whereDate('work_date', $this->attendanceDate)])
            ->orderBy('name')
            ->paginate(10);

        $stats = [
            'total' => Employee::query()->count(),
            'working' => Employee::query()->where('status', 'active')->count(),
            'checkedIn' => AttendanceRecord::query()->whereDate('attendance_date', $this->attendanceDate)->whereNotNull('check_in_at')->whereNull('check_out_at')->count(),
            'late' => AttendanceRecord::query()->whereDate('attendance_date', $this->attendanceDate)->where('status', 'late')->count(),
        ];

        return view('livewire.admin.employees-page', compact('employees', 'stats'))
            ->layout('layouts.admin', ['title' => 'Nhân viên & Chấm công']);
    }
}
