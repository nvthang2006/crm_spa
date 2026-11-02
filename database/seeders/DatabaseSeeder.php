<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Offering;
use App\Models\ResourceArea;
use App\Models\ServiceCategory;
use App\Models\ServiceResource;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@anmien.local'],
            [
                'name' => 'Nguyễn Văn Thắng',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $categories = collect([
            ['code' => 'MASSAGE', 'name' => 'Massage Body'],
            ['code' => 'FACIAL', 'name' => 'Chăm sóc da Facial'],
            ['code' => 'PACKAGE', 'name' => 'Gói thẻ liệu trình'],
        ])->mapWithKeys(fn (array $category): array => [
            $category['code'] => ServiceCategory::query()->updateOrCreate(['code' => $category['code']], $category),
        ]);

        $offerings = collect([
            ['code' => 'DV001', 'name' => 'Massage Body Tinh Dầu Thụy Điển', 'category' => 'MASSAGE', 'type' => 'service', 'duration_minutes' => 60, 'price' => 450000, 'sessions_total' => 1],
            ['code' => 'DV002', 'name' => 'Massage Trị Liệu Cổ Vai Gáy', 'category' => 'MASSAGE', 'type' => 'service', 'duration_minutes' => 75, 'price' => 600000, 'sessions_total' => 1],
            ['code' => 'DV003', 'name' => 'Chăm Sóc Da Trẻ Hóa Chuyên Sâu', 'category' => 'FACIAL', 'type' => 'service', 'duration_minutes' => 90, 'price' => 1200000, 'sessions_total' => 1],
            ['code' => 'PK001', 'name' => 'Gói Chăm Sóc Da Trẻ Hóa (12 Buổi)', 'category' => 'PACKAGE', 'type' => 'package', 'duration_minutes' => 90, 'price' => 12000000, 'sessions_total' => 12],
        ])->map(fn (array $offering): Offering => Offering::query()->updateOrCreate(
            ['code' => $offering['code']],
            [
                'name' => $offering['name'],
                'service_category_id' => $categories[$offering['category']]->id,
                'type' => $offering['type'],
                'duration_minutes' => $offering['duration_minutes'],
                'price' => $offering['price'],
                'sessions_total' => $offering['sessions_total'],
                'status' => 'active',
            ],
        ));

        $employees = collect([
            ['code' => 'NV001', 'name' => 'Lê Thị Thảo', 'job_title' => 'Kỹ thuật viên Trưởng', 'phone' => '0912888999', 'base_salary' => 8000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV002', 'name' => 'Trần Thu Hà', 'job_title' => 'KTV Chăm sóc da', 'phone' => '0988333444', 'base_salary' => 7000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV003', 'name' => 'Nguyễn Linh', 'job_title' => 'KTV Massage Body', 'phone' => '0903555666', 'base_salary' => 7500000, 'shift_name' => 'Ca chiều'],
            ['code' => 'NV004', 'name' => 'Phạm Hồng Vân', 'job_title' => 'KTV Trị liệu da', 'phone' => '0977123456', 'base_salary' => 7000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV005', 'name' => 'Đỗ Minh Quân', 'job_title' => 'KTV Massage Body', 'phone' => '0918777888', 'base_salary' => 7500000, 'shift_name' => 'Ca sáng'],
            ['code' => 'LT001', 'name' => 'Nguyễn Thúy Vi', 'job_title' => 'Lễ tân tiếp đón', 'phone' => '0909111222', 'base_salary' => 6500000, 'shift_name' => 'Ca sáng'],
        ])->mapWithKeys(fn (array $employee): array => [
            $employee['code'] => Employee::query()->updateOrCreate(['code' => $employee['code']], $employee),
        ]);

        $area = ResourceArea::query()->updateOrCreate(['code' => 'FACIAL'], [
            'name' => 'Khu Chăm Sóc Da Facial',
            'floor' => 'Tầng 1',
            'description' => 'Chăm sóc da công nghệ cao',
        ]);

        collect([
            ['code' => 'D01', 'name' => 'Giường Da 01'],
            ['code' => 'D02', 'name' => 'Giường Da 02'],
            ['code' => 'D03', 'name' => 'Giường Da 03'],
            ['code' => 'D04', 'name' => 'Giường Da 04'],
        ])->each(fn (array $resource): ServiceResource => ServiceResource::query()->updateOrCreate(
            ['code' => $resource['code']],
            ['resource_area_id' => $area->id, 'name' => $resource['name'], 'resource_type' => 'bed', 'status' => 'ready'],
        ));

        $customers = collect([
            ['code' => 'KH-0892', 'name' => 'Võ Ngọc Ánh', 'phone' => '0908999999', 'membership_tier' => 'Kim Cương', 'total_spend' => 42500000],
            ['code' => 'KH-0451', 'name' => 'Mai Lan', 'phone' => '0912345678', 'membership_tier' => 'Vàng', 'total_spend' => 18200000],
            ['code' => 'KH-0612', 'name' => 'Tuấn Minh', 'phone' => '0988888233', 'membership_tier' => 'Vàng', 'total_spend' => 15000000],
            ['code' => 'KH-0734', 'name' => 'Thu Cúc', 'phone' => '0903456789', 'membership_tier' => 'Bạc', 'total_spend' => 8200000],
        ])->mapWithKeys(fn (array $customer): array => [
            $customer['code'] => Customer::query()->updateOrCreate(['code' => $customer['code']], $customer),
        ]);

        $offerings->each(function (Offering $offering): void {
            $startsOn = today()->startOfMonth()->toDateString();
            $attributes = [
                'offering_id' => $offering->id,
                'beneficiary_type' => 'primary_technician',
            ];
            $values = [
                'beneficiary_employee_id' => null,
                'basis' => 'payment',
                'calculation_type' => 'percentage',
                'value' => $offering->type === 'package' ? 5 : 10,
                'starts_on' => $startsOn,
                'is_active' => true,
                'notes' => 'Quy tắc mặc định của cơ sở '.config('app.name'),
            ];
            $rule = CommissionRule::query()
                ->where($attributes)
                ->whereDate('starts_on', $startsOn)
                ->first();
            $rule?->update($values) ?? CommissionRule::query()->create($attributes + $values);
        });

        Appointment::query()->updateOrCreate(
            ['code' => 'LH-'.today()->format('Ymd').'-001'],
            [
                'customer_id' => $customers['KH-0451']->id,
                'offering_id' => $offerings->firstWhere('code', 'DV003')->id,
                'primary_employee_id' => $employees['NV004']->id,
                'service_resource_id' => ServiceResource::query()->where('code', 'D02')->value('id'),
                'appointment_date' => today(),
                'start_time' => '11:00',
                'end_time' => '12:30',
                'status' => 'arrived',
                'source' => 'booked',
                'payment_type' => 'service',
                'created_by' => $admin->id,
            ],
        );

        $this->call(SpaDemoDataSeeder::class);
    }
}
