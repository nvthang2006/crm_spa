<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'phone', 'email', 'job_title', 'shift_name', 'base_salary', 'status', 'notes'];

    protected function casts(): array
    {
        return ['base_salary' => 'integer'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function primaryAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'primary_employee_id');
    }

    public function supportAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'support_employee_id');
    }

    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function healthProfiles(): HasMany
    {
        return $this->hasMany(CustomerHealthProfile::class, 'preferred_employee_id');
    }
}
