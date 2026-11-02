<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\CustomerHealthProfile;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offering;
use App\Models\PackageUsage;
use App\Models\Payment;
use App\Models\PayrollAdjustment;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\ResourceArea;
use App\Models\ServiceCategory;
use App\Models\ServiceResource;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaDemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_idempotent_and_covers_operational_scenarios(): void
    {
        $this->seed();

        $firstCounts = $this->demoCounts();

        $this->seed();

        $this->assertSame($firstCounts, $this->demoCounts());
        $this->assertSame(10, Offering::query()->count());
        $this->assertSame(18, Employee::query()->count());
        $this->assertSame(20, ServiceResource::query()->count());
        $this->assertSame(24, Customer::query()->count());
        $this->assertSame(10, CustomerPackage::query()->count());
        $this->assertSame(31, Appointment::query()->count());
        $this->assertSame(0, Appointment::query()
            ->where('payment_type', 'package')
            ->whereHas('customerPackage', fn ($query) => $query->whereColumn('customer_packages.offering_id', '!=', 'appointments.offering_id'))
            ->count());
        $this->assertGreaterThanOrEqual(10, Invoice::query()->where('status', 'paid')->count());
        $this->assertGreaterThanOrEqual(1, CommissionEntry::query()->count());
        $this->assertGreaterThanOrEqual(2, PayrollPeriod::query()->count());

        $this->assertDatabaseHas('appointments', [
            'code' => 'LH-DEMO-003',
            'status' => 'in_service',
        ]);
        $this->assertDatabaseHas('invoices', [
            'code' => 'HD-DEMO-PKG-001',
            'status' => 'paid',
            'type' => 'package',
        ]);
        $this->assertDatabaseHas('package_usages', [
            'session_number' => 5,
            'direction' => 'restore',
        ]);
        $this->assertDatabaseHas('payroll_adjustments', [
            'code' => 'DC-DEMO-001',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('payroll_periods', [
            'code' => 'BL-DEMO-'.now()->format('Ym'),
            'status' => 'draft',
        ]);

        $profile = CustomerHealthProfile::query()->whereHas('customer', fn ($query) => $query->where('code', 'KH-1001'))->firstOrFail();
        $this->assertSame('Da khô, hơi nhạy cảm sau treatment.', $profile->current_condition);
    }

    /**
     * @return array<string, int>
     */
    private function demoCounts(): array
    {
        return [
            'categories' => ServiceCategory::query()->count(),
            'offerings' => Offering::query()->count(),
            'employees' => Employee::query()->count(),
            'areas' => ResourceArea::query()->count(),
            'resources' => ServiceResource::query()->count(),
            'customers' => Customer::query()->count(),
            'health_profiles' => CustomerHealthProfile::query()->count(),
            'packages' => CustomerPackage::query()->count(),
            'appointments' => Appointment::query()->count(),
            'package_usages' => PackageUsage::query()->count(),
            'invoices' => Invoice::query()->count(),
            'invoice_items' => InvoiceItem::query()->count(),
            'payments' => Payment::query()->count(),
            'commission_rules' => CommissionRule::query()->count(),
            'commission_entries' => CommissionEntry::query()->count(),
            'schedules' => WorkSchedule::query()->count(),
            'attendance' => AttendanceRecord::query()->count(),
            'payroll_periods' => PayrollPeriod::query()->count(),
            'payroll_lines' => PayrollLine::query()->count(),
            'payroll_adjustments' => PayrollAdjustment::query()->count(),
        ];
    }
}
