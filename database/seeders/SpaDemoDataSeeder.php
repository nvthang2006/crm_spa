<?php

namespace Database\Seeders;

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
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpaDemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@anmien.local')->firstOrFail();

        DB::transaction(function () use ($admin): void {
            $categories = $this->seedCategories();
            $offerings = $this->seedOfferings($categories);
            $employees = $this->seedEmployees();
            $this->seedCommissionRules($offerings, $employees);
            $areas = $this->seedAreas();
            $resources = $this->seedResources($areas);
            $customers = $this->seedCustomers();

            $this->seedHealthProfiles($customers, $employees, $admin);
            $packages = $this->seedPackages($customers, $offerings);
            $this->seedSchedulesAndAttendance($employees, $admin);
            $appointments = $this->seedAppointments($customers, $offerings, $employees, $resources, $packages, $admin);
            $this->seedPackageUsages($packages, $appointments, $employees, $admin);
            $invoices = $this->seedInvoices($appointments, $packages, $offerings, $employees, $admin);
            $this->seedCommissionEntries($invoices);
            $this->seedPayroll($employees, $admin);
            $this->refreshDemoCustomerTotals($customers);
        });
    }

    private function seedCategories(): Collection
    {
        $categories = [
            ['code' => 'MASSAGE', 'name' => 'Massage Body', 'is_active' => true],
            ['code' => 'FACIAL', 'name' => 'Chăm sóc da Facial', 'is_active' => true],
            ['code' => 'PACKAGE', 'name' => 'Gói thẻ liệu trình', 'is_active' => true],
            ['code' => 'WELLNESS', 'name' => 'Wellness & thư giãn', 'is_active' => true],
        ];

        return collect($categories)->mapWithKeys(fn (array $category): array => [
            $category['code'] => ServiceCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category,
            ),
        ]);
    }

    private function seedOfferings(Collection $categories): Collection
    {
        $offerings = [
            ['code' => 'DV001', 'name' => 'Massage Body Tinh Dầu Thụy Điển', 'category' => 'MASSAGE', 'type' => 'service', 'duration_minutes' => 60, 'price' => 450000, 'sessions_total' => 1, 'featured' => true, 'description' => 'Thư giãn toàn thân với tinh dầu thảo mộc dịu nhẹ.'],
            ['code' => 'DV002', 'name' => 'Massage Trị Liệu Cổ Vai Gáy', 'category' => 'MASSAGE', 'type' => 'service', 'duration_minutes' => 75, 'price' => 600000, 'sessions_total' => 1, 'featured' => false, 'description' => 'Tập trung giải phóng căng cứng vùng cổ, vai và gáy.'],
            ['code' => 'DV003', 'name' => 'Chăm Sóc Da Trẻ Hóa Chuyên Sâu', 'category' => 'FACIAL', 'type' => 'service', 'duration_minutes' => 90, 'price' => 1200000, 'sessions_total' => 1, 'featured' => true, 'description' => 'Làm sạch, cấp ẩm và phục hồi da theo liệu trình cá nhân.'],
            ['code' => 'DV004', 'name' => 'Massage Body Đá Nóng Thư Giãn', 'category' => 'MASSAGE', 'type' => 'service', 'duration_minutes' => 90, 'price' => 750000, 'sessions_total' => 1, 'featured' => true, 'description' => 'Đá nóng kết hợp massage giúp cơ thể thả lỏng sâu.'],
            ['code' => 'DV005', 'name' => 'Gội Đầu Dưỡng Sinh Trung Hoa', 'category' => 'WELLNESS', 'type' => 'service', 'duration_minutes' => 60, 'price' => 480000, 'sessions_total' => 1, 'featured' => false, 'description' => 'Gội đầu thảo dược, massage da đầu và vai gáy.'],
            ['code' => 'DV006', 'name' => 'Cấp Ẩm Da Chuyên Sâu', 'category' => 'FACIAL', 'type' => 'service', 'duration_minutes' => 60, 'price' => 850000, 'sessions_total' => 1, 'featured' => false, 'description' => 'Liệu trình cấp ẩm phục hồi cho da khô, nhạy cảm.'],
            ['code' => 'DV007', 'name' => 'Trị Liệu Cơ Sâu Giảm Đau', 'category' => 'MASSAGE', 'type' => 'service', 'duration_minutes' => 60, 'price' => 900000, 'sessions_total' => 1, 'featured' => false, 'description' => 'Kỹ thuật trị liệu cơ sâu dành cho khách làm việc cường độ cao.'],
            ['code' => 'PK001', 'name' => 'Gói Chăm Sóc Da Trẻ Hóa (12 Buổi)', 'category' => 'PACKAGE', 'type' => 'package', 'duration_minutes' => 90, 'price' => 12000000, 'sessions_total' => 12, 'featured' => true, 'description' => 'Gói liệu trình trẻ hóa da 12 buổi, sử dụng linh hoạt trong 12 tháng.'],
            ['code' => 'PK002', 'name' => 'Gói Massage Đá Nóng (10 Buổi)', 'category' => 'PACKAGE', 'type' => 'package', 'duration_minutes' => 90, 'price' => 6500000, 'sessions_total' => 10, 'featured' => true, 'description' => 'Gói massage đá nóng 10 buổi cho khách cần phục hồi định kỳ.'],
            ['code' => 'PK003', 'name' => 'Gói Facial Phục Hồi (8 Buổi)', 'category' => 'PACKAGE', 'type' => 'package', 'duration_minutes' => 60, 'price' => 6800000, 'sessions_total' => 8, 'featured' => false, 'description' => 'Gói chăm sóc và phục hồi hàng rào bảo vệ da 8 buổi.'],
        ];

        return collect($offerings)->mapWithKeys(function (array $offering) use ($categories): array {
            $model = Offering::query()->updateOrCreate(
                ['code' => $offering['code']],
                [
                    'name' => $offering['name'],
                    'service_category_id' => $categories[$offering['category']]->id,
                    'type' => $offering['type'],
                    'duration_minutes' => $offering['duration_minutes'],
                    'price' => $offering['price'],
                    'sessions_total' => $offering['sessions_total'],
                    'status' => 'active',
                    'featured' => $offering['featured'],
                    'description' => $offering['description'],
                ],
            );

            return [$offering['code'] => $model];
        });
    }

    private function seedEmployees(): Collection
    {
        $employees = [
            ['code' => 'NV001', 'name' => 'Lê Thị Thảo', 'job_title' => 'Kỹ thuật viên Trưởng', 'phone' => '0912888999', 'email' => 'thao.le@anmien.local', 'base_salary' => 8000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV002', 'name' => 'Trần Thu Hà', 'job_title' => 'KTV Chăm sóc da', 'phone' => '0988333444', 'email' => 'ha.tran@anmien.local', 'base_salary' => 7000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV003', 'name' => 'Nguyễn Linh', 'job_title' => 'KTV Massage Body', 'phone' => '0903555666', 'email' => 'linh.nguyen@anmien.local', 'base_salary' => 7500000, 'shift_name' => 'Ca chiều'],
            ['code' => 'NV004', 'name' => 'Phạm Hồng Vân', 'job_title' => 'KTV Trị liệu da', 'phone' => '0977123456', 'email' => 'van.pham@anmien.local', 'base_salary' => 7000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV005', 'name' => 'Đỗ Minh Quân', 'job_title' => 'KTV Massage Body', 'phone' => '0918777888', 'email' => 'quan.do@anmien.local', 'base_salary' => 7500000, 'shift_name' => 'Ca sáng'],
            ['code' => 'LT001', 'name' => 'Nguyễn Thúy Vi', 'job_title' => 'Lễ tân tiếp đón', 'phone' => '0909111222', 'email' => 'vi.nguyen@anmien.local', 'base_salary' => 6500000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV007', 'name' => 'Bùi Thanh Hương', 'job_title' => 'KTV Massage Body', 'phone' => '0904001007', 'email' => 'huong.bui@anmien.local', 'base_salary' => 7200000, 'shift_name' => 'Ca chiều'],
            ['code' => 'NV008', 'name' => 'Vũ Hoàng Nam', 'job_title' => 'KTV Chăm sóc da', 'phone' => '0904001008', 'email' => 'nam.vu@anmien.local', 'base_salary' => 7300000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV009', 'name' => 'Đặng Minh Anh', 'job_title' => 'KTV Trị liệu', 'phone' => '0904001009', 'email' => 'anh.dang@anmien.local', 'base_salary' => 7600000, 'shift_name' => 'Ca chiều'],
            ['code' => 'NV010', 'name' => 'Hồ Ngọc Mai', 'job_title' => 'KTV Facial', 'phone' => '0904001010', 'email' => 'mai.ho@anmien.local', 'base_salary' => 7000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV011', 'name' => 'Đinh Quốc Bảo', 'job_title' => 'KTV Massage Body', 'phone' => '0904001011', 'email' => 'bao.dinh@anmien.local', 'base_salary' => 7400000, 'shift_name' => 'Ca chiều'],
            ['code' => 'NV012', 'name' => 'Nguyễn Khánh Ly', 'job_title' => 'KTV Gội đầu dưỡng sinh', 'phone' => '0904001012', 'email' => 'ly.nguyen@anmien.local', 'base_salary' => 6800000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV013', 'name' => 'Lâm Gia Hân', 'job_title' => 'KTV Facial', 'phone' => '0904001013', 'email' => 'han.lam@anmien.local', 'base_salary' => 7100000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV014', 'name' => 'Phan Tuấn Kiệt', 'job_title' => 'KTV Trị liệu cơ', 'phone' => '0904001014', 'email' => 'kiet.phan@anmien.local', 'base_salary' => 7800000, 'shift_name' => 'Ca chiều'],
            ['code' => 'NV015', 'name' => 'Trịnh Ngọc Trâm', 'job_title' => 'KTV Massage Body', 'phone' => '0904001015', 'email' => 'tram.trinh@anmien.local', 'base_salary' => 7200000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV016', 'name' => 'Đoàn Minh Tú', 'job_title' => 'KTV Wellness', 'phone' => '0904001016', 'email' => 'tu.doan@anmien.local', 'base_salary' => 6900000, 'shift_name' => 'Ca chiều'],
            ['code' => 'NV017', 'name' => 'Mai Phương Thảo', 'job_title' => 'KTV Chăm sóc da', 'phone' => '0904001017', 'email' => 'thao.mai@anmien.local', 'base_salary' => 7000000, 'shift_name' => 'Ca sáng'],
            ['code' => 'NV018', 'name' => 'Lê Nhật Minh', 'job_title' => 'KTV Massage Body', 'phone' => '0904001018', 'email' => 'minh.le@anmien.local', 'base_salary' => 7350000, 'shift_name' => 'Ca chiều'],
        ];

        return collect($employees)->mapWithKeys(fn (array $employee): array => [
            $employee['code'] => Employee::query()->updateOrCreate(
                ['code' => $employee['code']],
                $employee + ['status' => 'active'],
            ),
        ]);
    }

    private function seedAreas(): Collection
    {
        $areas = [
            ['code' => 'VIP', 'name' => 'Phòng VIP Thư Giãn', 'floor' => 'Tầng 2', 'description' => 'Không gian riêng tư cho liệu trình cao cấp.'],
            ['code' => 'FACIAL', 'name' => 'Khu Chăm Sóc Da Facial', 'floor' => 'Tầng 1', 'description' => 'Chăm sóc da công nghệ cao.'],
            ['code' => 'BODY', 'name' => 'Khu Massage Body', 'floor' => 'Tầng 1', 'description' => 'Khu massage body và trị liệu cơ.'],
            ['code' => 'WELLNESS', 'name' => 'Khu Wellness', 'floor' => 'Tầng 2', 'description' => 'Gội đầu dưỡng sinh và thư giãn nhẹ.'],
        ];

        return collect($areas)->mapWithKeys(fn (array $area): array => [
            $area['code'] => ResourceArea::query()->updateOrCreate(
                ['code' => $area['code']],
                $area + ['status' => 'active'],
            ),
        ]);
    }

    private function seedResources(Collection $areas): Collection
    {
        $resources = [
            ['code' => 'D01', 'name' => 'Giường Da 01', 'area' => 'FACIAL', 'resource_type' => 'bed', 'status' => 'ready'],
            ['code' => 'D02', 'name' => 'Giường Da 02', 'area' => 'FACIAL', 'resource_type' => 'bed', 'status' => 'busy'],
            ['code' => 'D03', 'name' => 'Giường Da 03', 'area' => 'FACIAL', 'resource_type' => 'bed', 'status' => 'cleaning'],
            ['code' => 'D04', 'name' => 'Giường Da 04', 'area' => 'FACIAL', 'resource_type' => 'bed', 'status' => 'ready'],
            ['code' => 'VIP01', 'name' => 'Phòng VIP 01', 'area' => 'VIP', 'resource_type' => 'room', 'status' => 'ready'],
            ['code' => 'VIP02', 'name' => 'Phòng VIP 02', 'area' => 'VIP', 'resource_type' => 'room', 'status' => 'busy'],
            ['code' => 'VIP03', 'name' => 'Phòng VIP 03', 'area' => 'VIP', 'resource_type' => 'room', 'status' => 'maintenance', 'maintenance_note' => 'Bảo trì máy xông đá nóng', 'maintenance_until' => 'tomorrow 18:00'],
            ['code' => 'VIP04', 'name' => 'Phòng VIP 04', 'area' => 'VIP', 'resource_type' => 'room', 'status' => 'ready'],
            ['code' => 'BODY01', 'name' => 'Giường Body 01', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'ready'],
            ['code' => 'BODY02', 'name' => 'Giường Body 02', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'ready'],
            ['code' => 'BODY03', 'name' => 'Giường Body 03', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'busy'],
            ['code' => 'BODY04', 'name' => 'Giường Body 04', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'cleaning'],
            ['code' => 'BODY05', 'name' => 'Giường Body 05', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'ready'],
            ['code' => 'BODY06', 'name' => 'Giường Body 06', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'ready'],
            ['code' => 'BODY07', 'name' => 'Giường Body 07', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'ready'],
            ['code' => 'BODY08', 'name' => 'Giường Body 08', 'area' => 'BODY', 'resource_type' => 'bed', 'status' => 'maintenance', 'maintenance_note' => 'Thay đệm và vệ sinh chuyên sâu', 'maintenance_until' => 'next week 12:00'],
            ['code' => 'WELL01', 'name' => 'Ghế Gội Dưỡng Sinh 01', 'area' => 'WELLNESS', 'resource_type' => 'chair', 'status' => 'ready'],
            ['code' => 'WELL02', 'name' => 'Ghế Gội Dưỡng Sinh 02', 'area' => 'WELLNESS', 'resource_type' => 'chair', 'status' => 'ready'],
            ['code' => 'WELL03', 'name' => 'Ghế Gội Dưỡng Sinh 03', 'area' => 'WELLNESS', 'resource_type' => 'chair', 'status' => 'busy'],
            ['code' => 'WELL04', 'name' => 'Ghế Gội Dưỡng Sinh 04', 'area' => 'WELLNESS', 'resource_type' => 'chair', 'status' => 'cleaning'],
        ];

        return collect($resources)->mapWithKeys(function (array $resource) use ($areas): array {
            $maintenanceUntil = $resource['maintenance_until'] ?? null;
            $resourceModel = ServiceResource::query()->updateOrCreate(
                ['code' => $resource['code']],
                [
                    'resource_area_id' => $areas[$resource['area']]->id,
                    'name' => $resource['name'],
                    'resource_type' => $resource['resource_type'],
                    'status' => $resource['status'],
                    'maintenance_note' => $resource['maintenance_note'] ?? null,
                    'maintenance_until' => $maintenanceUntil ? Carbon::parse($maintenanceUntil) : null,
                ],
            );

            return [$resource['code'] => $resourceModel];
        });
    }

    private function seedCustomers(): Collection
    {
        $customers = [
            ['code' => 'KH-0892', 'name' => 'Võ Ngọc Ánh', 'phone' => '0908999999', 'email' => 'anh.vo@example.test', 'membership_tier' => 'Kim Cương', 'total_spend' => 42500000, 'address' => 'Thảo Điền, TP. Hồ Chí Minh'],
            ['code' => 'KH-0451', 'name' => 'Mai Lan', 'phone' => '0912345678', 'email' => 'lan.mai@example.test', 'membership_tier' => 'Vàng', 'total_spend' => 18200000, 'address' => 'Quận 3, TP. Hồ Chí Minh'],
            ['code' => 'KH-0612', 'name' => 'Tuấn Minh', 'phone' => '0988888233', 'email' => 'minh.tuan@example.test', 'membership_tier' => 'Vàng', 'total_spend' => 15000000, 'address' => 'Bình Thạnh, TP. Hồ Chí Minh'],
            ['code' => 'KH-0734', 'name' => 'Thu Cúc', 'phone' => '0903456789', 'email' => 'cuc.thu@example.test', 'membership_tier' => 'Bạc', 'total_spend' => 8200000, 'address' => 'Quận 1, TP. Hồ Chí Minh'],
            ['code' => 'KH-1001', 'name' => 'Nguyễn Hà My', 'phone' => '0905101001', 'email' => 'my.nguyen@example.test', 'membership_tier' => 'Kim Cương', 'address' => 'Thảo Điền, TP. Hồ Chí Minh'],
            ['code' => 'KH-1002', 'name' => 'Trần Quốc Khải', 'phone' => '0905101002', 'email' => 'khai.tran@example.test', 'membership_tier' => 'Vàng', 'address' => 'Quận 7, TP. Hồ Chí Minh'],
            ['code' => 'KH-1003', 'name' => 'Lê Quỳnh Như', 'phone' => '0905101003', 'email' => 'nhu.le@example.test', 'membership_tier' => 'Bạc', 'address' => 'Phú Nhuận, TP. Hồ Chí Minh'],
            ['code' => 'KH-1004', 'name' => 'Phạm Gia Huy', 'phone' => '0905101004', 'email' => 'huy.pham@example.test', 'membership_tier' => 'Vàng', 'address' => 'Quận 2, TP. Hồ Chí Minh'],
            ['code' => 'KH-1005', 'name' => 'Đỗ Thanh Thủy', 'phone' => '0905101005', 'email' => 'thuy.do@example.test', 'membership_tier' => 'Thành viên', 'address' => 'Quận 10, TP. Hồ Chí Minh'],
            ['code' => 'KH-1006', 'name' => 'Vũ Minh Châu', 'phone' => '0905101006', 'email' => 'chau.vu@example.test', 'membership_tier' => 'Bạc', 'address' => 'Tân Bình, TP. Hồ Chí Minh'],
            ['code' => 'KH-1007', 'name' => 'Hoàng Anh Tuấn', 'phone' => '0905101007', 'email' => 'tuan.hoang@example.test', 'membership_tier' => 'Thành viên', 'address' => 'Gò Vấp, TP. Hồ Chí Minh'],
            ['code' => 'KH-1008', 'name' => 'Ngô Bảo Trân', 'phone' => '0905101008', 'email' => 'tran.ngo@example.test', 'membership_tier' => 'Vàng', 'address' => 'Quận 4, TP. Hồ Chí Minh'],
            ['code' => 'KH-1009', 'name' => 'Dương Hải Yến', 'phone' => '0905101009', 'email' => 'yen.duong@example.test', 'membership_tier' => 'Bạc', 'address' => 'Quận 6, TP. Hồ Chí Minh'],
            ['code' => 'KH-1010', 'name' => 'Đinh Khánh Linh', 'phone' => '0905101010', 'email' => 'linh.dinh@example.test', 'membership_tier' => 'Kim Cương', 'address' => 'Bình Thạnh, TP. Hồ Chí Minh'],
            ['code' => 'KH-1011', 'name' => 'Bùi Đức Anh', 'phone' => '0905101011', 'email' => 'anh.bui@example.test', 'membership_tier' => 'Thành viên', 'address' => 'Thủ Đức, TP. Hồ Chí Minh'],
            ['code' => 'KH-1012', 'name' => 'Lâm Thùy Dung', 'phone' => '0905101012', 'email' => 'dung.lam@example.test', 'membership_tier' => 'Vàng', 'address' => 'Quận 8, TP. Hồ Chí Minh'],
            ['code' => 'KH-1013', 'name' => 'Phan Minh Khang', 'phone' => '0905101013', 'email' => 'khang.phan@example.test', 'membership_tier' => 'Bạc', 'address' => 'Quận 11, TP. Hồ Chí Minh'],
            ['code' => 'KH-1014', 'name' => 'Tạ Ngọc Hân', 'phone' => '0905101014', 'email' => 'han.ta@example.test', 'membership_tier' => 'Thành viên', 'address' => 'Bình Tân, TP. Hồ Chí Minh'],
            ['code' => 'KH-1015', 'name' => 'Trương Hoài Nam', 'phone' => '0905101015', 'email' => 'nam.truong@example.test', 'membership_tier' => 'Vàng', 'address' => 'Quận 5, TP. Hồ Chí Minh'],
            ['code' => 'KH-1016', 'name' => 'Hà Ngọc Diệp', 'phone' => '0905101016', 'email' => 'diep.ha@example.test', 'membership_tier' => 'Bạc', 'address' => 'Quận 12, TP. Hồ Chí Minh'],
            ['code' => 'KH-1017', 'name' => 'Nguyễn Trung Hiếu', 'phone' => '0905101017', 'email' => 'hieu.nguyen@example.test', 'membership_tier' => 'Thành viên', 'address' => 'Tân Phú, TP. Hồ Chí Minh'],
            ['code' => 'KH-1018', 'name' => 'Cao Mỹ Duyên', 'phone' => '0905101018', 'email' => 'duyen.cao@example.test', 'membership_tier' => 'Vàng', 'address' => 'Quận 9, TP. Hồ Chí Minh'],
            ['code' => 'KH-1019', 'name' => 'Lý Nhật Quang', 'phone' => '0905101019', 'email' => 'quang.ly@example.test', 'membership_tier' => 'Bạc', 'address' => 'Quận 1, TP. Hồ Chí Minh'],
            ['code' => 'KH-1020', 'name' => 'Mai Uyên Nhi', 'phone' => '0905101020', 'email' => 'nhi.mai@example.test', 'membership_tier' => 'Thành viên', 'address' => 'Quận 3, TP. Hồ Chí Minh'],
        ];

        return collect($customers)->mapWithKeys(fn (array $customer): array => [
            $customer['code'] => Customer::query()->updateOrCreate(
                ['code' => $customer['code']],
                $customer + ['status' => 'active', 'notes' => null],
            ),
        ]);
    }

    private function seedHealthProfiles(Collection $customers, Collection $employees, User $admin): void
    {
        $profiles = [
            ['customer' => 'KH-0892', 'employee' => 'NV004', 'allergy_notes' => 'Dị ứng nhẹ với hương liệu tổng hợp.', 'current_condition' => 'Da hỗn hợp, vùng chữ T dễ đổ dầu.', 'contraindications' => 'Không dùng sản phẩm có nồng độ AHA cao.', 'care_notes' => 'Ưu tiên liệu trình cấp ẩm và phục hồi.'],
            ['customer' => 'KH-1001', 'employee' => 'NV002', 'allergy_notes' => 'Không ghi nhận dị ứng.', 'current_condition' => 'Da khô, hơi nhạy cảm sau treatment.', 'contraindications' => 'Tránh massage mạnh vùng má trong 48 giờ.', 'care_notes' => 'Nhắc khách bôi kem chống nắng mỗi sáng.'],
            ['customer' => 'KH-1002', 'employee' => 'NV003', 'allergy_notes' => 'Dị ứng với dầu khuynh diệp.', 'current_condition' => 'Căng cơ cổ vai gáy do làm việc văn phòng.', 'contraindications' => 'Không dùng đá quá nóng.', 'care_notes' => 'Tập trung vùng bả vai, hỏi lại mức lực sau 20 phút.'],
            ['customer' => 'KH-1003', 'employee' => 'NV010', 'allergy_notes' => 'Da phản ứng với cồn khô.', 'current_condition' => 'Da mụn ẩn nhẹ vùng cằm.', 'contraindications' => 'Tránh nặn mụn viêm.', 'care_notes' => 'Dùng sản phẩm làm dịu, không chà xát mạnh.'],
            ['customer' => 'KH-1004', 'employee' => 'NV007', 'allergy_notes' => 'Không ghi nhận dị ứng.', 'current_condition' => 'Thường xuyên đau thắt lưng nhẹ.', 'contraindications' => 'Tránh ấn trực tiếp lên vùng đau cấp.', 'care_notes' => 'Khuyến nghị liệu trình trị liệu cơ sâu 4 buổi.'],
            ['customer' => 'KH-1005', 'employee' => 'NV013', 'allergy_notes' => 'Dị ứng hương hoa hồng.', 'current_condition' => 'Da thiếu ẩm, xỉn màu.', 'contraindications' => 'Không dùng tinh chất có hương hoa hồng.', 'care_notes' => 'Ưu tiên mặt nạ phục hồi không mùi.'],
            ['customer' => 'KH-1010', 'employee' => 'NV001', 'allergy_notes' => 'Không ghi nhận dị ứng.', 'current_condition' => 'Da khỏe, mục tiêu duy trì độ sáng.', 'contraindications' => null, 'care_notes' => 'Khách thích kỹ thuật viên nói nhỏ và phòng yên tĩnh.'],
            ['customer' => 'KH-1015', 'employee' => 'NV005', 'allergy_notes' => 'Nhạy cảm với tinh dầu bạc hà.', 'current_condition' => 'Căng cơ bắp chân sau khi chạy bộ.', 'contraindications' => 'Tránh kéo giãn quá biên độ.', 'care_notes' => 'Bổ sung chườm ấm cuối buổi.'],
        ];

        foreach ($profiles as $profile) {
            CustomerHealthProfile::query()->updateOrCreate(
                ['customer_id' => $customers[$profile['customer']]->id],
                [
                    'preferred_employee_id' => $employees[$profile['employee']]->id,
                    'last_updated_by' => $admin->id,
                    'allergy_notes' => $profile['allergy_notes'],
                    'current_condition' => $profile['current_condition'],
                    'contraindications' => $profile['contraindications'],
                    'care_notes' => $profile['care_notes'],
                ],
            );
        }
    }

    private function seedPackages(Collection $customers, Collection $offerings): Collection
    {
        $today = today();
        $packages = [
            ['code' => 'THE-DEMO-001', 'customer' => 'KH-1001', 'offering' => 'PK001', 'purchased_on' => $today->copy()->subDays(42), 'starts_on' => $today->copy()->subDays(40), 'expires_on' => $today->copy()->addMonths(8), 'sessions_used' => 4, 'status' => 'active', 'notes' => 'Khách mua tại quầy sau buổi tư vấn da.'],
            ['code' => 'THE-DEMO-002', 'customer' => 'KH-1002', 'offering' => 'PK002', 'purchased_on' => $today->copy()->subDays(28), 'starts_on' => $today->copy()->subDays(27), 'expires_on' => $today->copy()->addMonths(10), 'sessions_used' => 4, 'status' => 'active', 'notes' => 'Có 4 buổi còn lại; session 5 có lịch sử hoàn tác để demo.'],
            ['code' => 'THE-DEMO-003', 'customer' => 'KH-1003', 'offering' => 'PK003', 'purchased_on' => $today->copy()->subDays(18), 'starts_on' => $today->copy()->subDays(17), 'expires_on' => $today->copy()->addMonths(9), 'sessions_used' => 2, 'status' => 'active', 'notes' => 'Liệu trình phục hồi da sau mụn.'],
            ['code' => 'THE-DEMO-004', 'customer' => 'KH-1004', 'offering' => 'PK002', 'purchased_on' => $today->copy()->subDays(65), 'starts_on' => $today->copy()->subDays(64), 'expires_on' => $today->copy()->addMonths(6), 'sessions_used' => 7, 'status' => 'active', 'notes' => 'Khách đặt lịch cố định mỗi tuần.'],
            ['code' => 'THE-DEMO-005', 'customer' => 'KH-1005', 'offering' => 'PK003', 'purchased_on' => $today->copy()->subDays(120), 'starts_on' => $today->copy()->subDays(119), 'expires_on' => $today->copy()->addMonths(3), 'sessions_used' => 5, 'status' => 'active', 'notes' => 'Theo dõi độ ẩm da sau mỗi buổi.'],
            ['code' => 'THE-DEMO-006', 'customer' => 'KH-1010', 'offering' => 'PK001', 'purchased_on' => $today->copy()->subDays(210), 'starts_on' => $today->copy()->subDays(208), 'expires_on' => $today->copy()->addMonths(2), 'sessions_used' => 10, 'status' => 'active', 'notes' => 'Còn 2 buổi trong gói chăm sóc da.'],
            ['code' => 'THE-DEMO-007', 'customer' => 'KH-1015', 'offering' => 'PK002', 'purchased_on' => $today->copy()->subDays(370), 'starts_on' => $today->copy()->subDays(368), 'expires_on' => $today->copy()->subDays(5), 'sessions_used' => 10, 'status' => 'expired', 'notes' => 'Gói hết hạn, chỉ giữ lịch sử sử dụng.'],
            ['code' => 'THE-DEMO-008', 'customer' => 'KH-1008', 'offering' => 'PK003', 'purchased_on' => $today->copy()->subDays(25), 'starts_on' => $today->copy()->subDays(24), 'expires_on' => $today->copy()->addMonths(10), 'sessions_used' => 1, 'status' => 'active', 'notes' => 'Khách mới, đã dùng buổi đầu tiên.'],
            ['code' => 'THE-DEMO-009', 'customer' => 'KH-1012', 'offering' => 'PK001', 'purchased_on' => $today->copy()->subDays(450), 'starts_on' => $today->copy()->subDays(448), 'expires_on' => $today->copy()->subDays(80), 'sessions_used' => 12, 'status' => 'completed', 'notes' => 'Đã hoàn tất toàn bộ liệu trình.'],
            ['code' => 'THE-DEMO-010', 'customer' => 'KH-1018', 'offering' => 'PK002', 'purchased_on' => $today->copy()->subDays(12), 'starts_on' => $today->copy()->subDays(11), 'expires_on' => $today->copy()->addMonths(11), 'sessions_used' => 0, 'status' => 'active', 'notes' => 'Gói mới kích hoạt, chưa sử dụng.'],
        ];

        return collect($packages)->mapWithKeys(function (array $package) use ($customers, $offerings): array {
            $model = CustomerPackage::query()->updateOrCreate(
                ['code' => $package['code']],
                [
                    'customer_id' => $customers[$package['customer']]->id,
                    'offering_id' => $offerings[$package['offering']]->id,
                    'purchased_on' => $package['purchased_on']->toDateString(),
                    'starts_on' => $package['starts_on']->toDateString(),
                    'expires_on' => $package['expires_on']->toDateString(),
                    'sessions_total' => $offerings[$package['offering']]->sessions_total,
                    'sessions_used' => $package['sessions_used'],
                    'unit_price' => $offerings[$package['offering']]->price ?? 0,
                    'status' => $package['status'],
                    'notes' => $package['notes'],
                ],
            );

            return [$package['code'] => $model];
        });
    }

    private function seedCommissionRules(Collection $offerings, Collection $employees): void
    {
        $currentStart = today()->startOfMonth()->toDateString();
        $previousStart = today()->subMonth()->startOfMonth()->toDateString();
        $previousEnd = today()->startOfMonth()->subDay()->toDateString();

        foreach ($offerings as $offering) {
            $value = $offering->type === 'package' ? 5 : 10;

            $this->upsertCommissionRule(
                $offering,
                null,
                'primary_technician',
                'payment',
                'percentage',
                $value,
                $currentStart,
                null,
                'Quy tắc mặc định của cơ sở '.config('app.name'),
            );

            $this->upsertCommissionRule(
                $offering,
                null,
                'primary_technician',
                'payment',
                'percentage',
                $value,
                $previousStart,
                $previousEnd,
                'Quy tắc lịch sử tháng trước dùng cho báo cáo mẫu',
            );
        }

        $this->upsertCommissionRule(
            $offerings['DV004'],
            null,
            'support_technician',
            'completion',
            'percentage',
            3,
            $currentStart,
            null,
            'Thưởng KTV hỗ trợ khi hoàn thành massage đá nóng',
        );

        $this->upsertCommissionRule(
            $offerings['DV006'],
            null,
            'primary_technician',
            'completion',
            'fixed',
            50000,
            $currentStart,
            null,
            'Thưởng cố định cho liệu trình cấp ẩm chuyên sâu',
        );

        $this->upsertCommissionRule(
            $offerings['PK002'],
            $employees['NV001'],
            'specific_employee',
            'payment',
            'percentage',
            2,
            $currentStart,
            null,
            'Thưởng tư vấn gói massage cho KTV trưởng',
        );

        $this->upsertCommissionRule(
            $offerings['PK002'],
            $employees['NV001'],
            'specific_employee',
            'payment',
            'percentage',
            2,
            $previousStart,
            $previousEnd,
            'Thưởng lịch sử tư vấn gói massage tháng trước',
        );
    }

    private function upsertCommissionRule(
        Offering $offering,
        ?Employee $beneficiaryEmployee,
        string $beneficiaryType,
        string $basis,
        string $calculationType,
        int|float $value,
        string $startsOn,
        ?string $endsOn,
        string $notes,
    ): CommissionRule {
        $query = CommissionRule::query()
            ->where('offering_id', $offering->id)
            ->where('beneficiary_type', $beneficiaryType)
            ->where('basis', $basis)
            ->where('calculation_type', $calculationType)
            ->whereDate('starts_on', $startsOn);

        if ($beneficiaryEmployee) {
            $query->where('beneficiary_employee_id', $beneficiaryEmployee->id);
        } else {
            $query->whereNull('beneficiary_employee_id');
        }

        $attributes = [
            'offering_id' => $offering->id,
            'beneficiary_employee_id' => $beneficiaryEmployee?->id,
            'beneficiary_type' => $beneficiaryType,
            'basis' => $basis,
            'calculation_type' => $calculationType,
            'value' => $value,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => true,
            'notes' => $notes,
        ];

        $rule = $query->first();

        if ($rule) {
            $rule->update($attributes);

            return $rule->fresh();
        }

        return CommissionRule::query()->create($attributes);
    }

    private function seedSchedulesAndAttendance(Collection $employees, User $admin): void
    {
        $today = today();

        foreach (range(0, 6) as $dayOffset) {
            $date = $today->copy()->subDays($dayOffset);

            if ($date->isSunday()) {
                continue;
            }

            foreach ($employees->values() as $index => $employee) {
                [$startsAt, $endsAt] = $employee->shift_name === 'Ca chiều'
                    ? ['13:00', '21:00']
                    : ['08:00', '17:00'];

                $schedule = WorkSchedule::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', $date->toDateString())
                    ->first();

                $scheduleValues = [
                    'employee_id' => $employee->id,
                    'work_date' => $date->toDateString(),
                    'shift_name' => $employee->shift_name ?: 'Ca sáng',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'scheduled',
                    'notes' => $date->isToday() ? 'Lịch làm việc trong ngày' : 'Lịch làm việc mẫu đã chốt',
                ];

                if ($schedule) {
                    $schedule->update($scheduleValues);
                } else {
                    $schedule = WorkSchedule::query()->create($scheduleValues);
                }

                $isAbsent = $date->isToday() && $index % 9 === 0;
                $isLeave = ! $date->isToday() && $date->isSaturday() && $index % 11 === 0;
                $isLate = ! $isAbsent && ! $isLeave && (($index + $date->day) % 7 === 0);
                $lateMinutes = $isLate ? 8 + ($index % 3) * 7 : 0;
                $checkIn = null;
                $checkOut = null;

                if (! $isAbsent && ! $isLeave) {
                    $checkIn = Carbon::parse($date->toDateString().' '.$startsAt)->addMinutes($lateMinutes);

                    if (! $date->isToday()) {
                        $checkOut = Carbon::parse($date->toDateString().' '.$endsAt)->subMinutes(($index % 3) * 5);
                    }
                }

                $attendanceValues = [
                    'employee_id' => $employee->id,
                    'work_schedule_id' => $schedule->id,
                    'recorded_by' => $admin->id,
                    'attendance_date' => $date->toDateString(),
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'status' => $isLeave ? 'leave' : ($isAbsent ? 'absent' : ($isLate ? 'late' : 'present')),
                    'late_minutes' => $lateMinutes,
                    'notes' => $isLeave ? 'Nghỉ phép đã đăng ký' : ($isAbsent ? 'Chưa check-in trong ngày' : null),
                ];

                $attendance = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('attendance_date', $date->toDateString())
                    ->first();

                if ($attendance) {
                    $attendance->update($attendanceValues);
                } else {
                    AttendanceRecord::query()->create($attendanceValues);
                }
            }
        }
    }

    private function seedAppointments(
        Collection $customers,
        Collection $offerings,
        Collection $employees,
        Collection $resources,
        Collection $packages,
        User $admin,
    ): Collection {
        $today = today();
        $statuses = [
            'scheduled', 'arrived', 'in_service', 'completed_pending_payment', 'paid', 'package_used',
            'cancelled', 'no_show', 'scheduled', 'arrived', 'in_service', 'completed_pending_payment',
            'paid', 'package_used', 'scheduled', 'completed_pending_payment', 'paid', 'package_used',
            'cancelled', 'arrived', 'scheduled', 'paid', 'package_used', 'completed_pending_payment',
            'no_show', 'scheduled', 'arrived', 'paid', 'package_used', 'cancelled',
        ];
        $dateOffsets = [-1, 1, -3, 3, -2, 2, -4, 4, -5, 5, -6, 6, 0, -2, 2, -7, 7, -8];
        $serviceCodes = ['DV001', 'DV002', 'DV003', 'DV004', 'DV005', 'DV006', 'DV007'];
        $customerCodes = $customers->keys()->filter(fn (string $code): bool => str_starts_with($code, 'KH-10'))->values();
        $availableResources = $resources->filter(fn (ServiceResource $resource): bool => $resource->status !== 'maintenance')->values();
        $packageCodes = $packages->keys()->values();
        $appointments = collect();

        foreach ($statuses as $index => $status) {
            $offering = $offerings[$serviceCodes[$index % count($serviceCodes)]];
            $package = null;

            if ($status === 'package_used' || ($status === 'completed_pending_payment' && $index % 4 === 0)) {
                $package = $packages[$packageCodes[$index % max(1, $packageCodes->count())]];
                $customer = $customers->firstWhere('id', $package->customer_id);
                $offering = $offerings->firstWhere('id', $package->offering_id);
            } else {
                $customer = $customers[$customerCodes[$index % max(1, $customerCodes->count())]];
            }

            $appointmentDate = $index < 12
                ? $today->copy()
                : $today->copy()->addDays($dateOffsets[$index % count($dateOffsets)]);
            $startMinutes = 8 * 60 + (($index % 10) * 75);
            $startTime = sprintf('%02d:%02d', intdiv($startMinutes, 60), $startMinutes % 60);
            $endDateTime = Carbon::parse($appointmentDate->toDateString().' '.$startTime)->addMinutes($offering->duration_minutes);
            $startDateTime = Carbon::parse($appointmentDate->toDateString().' '.$startTime);
            $primaryEmployee = $employees->values()->get($index % max(1, $employees->count()));
            $supportEmployee = $index % 4 === 0
                ? $employees->values()->get(($index + 3) % max(1, $employees->count()))
                : null;
            $resource = $index % 7 === 0
                ? null
                : $availableResources->get($index % max(1, $availableResources->count()));
            $isCompleted = in_array($status, ['completed_pending_payment', 'paid', 'package_used'], true);
            $isStarted = in_array($status, ['in_service', 'completed_pending_payment', 'paid', 'package_used'], true);
            $isCheckedIn = $isStarted || $status === 'arrived';

            $values = [
                'customer_id' => $customer->id,
                'offering_id' => $offering->id,
                'primary_employee_id' => $primaryEmployee?->id,
                'support_employee_id' => $supportEmployee?->id,
                'service_resource_id' => $resource?->id,
                'customer_package_id' => $package?->id,
                'created_by' => $admin->id,
                'appointment_date' => $appointmentDate->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endDateTime->format('H:i'),
                'status' => $status,
                'source' => ['booked', 'walk_in', 'phone', 'online'][$index % 4],
                'payment_type' => $package ? 'package' : 'service',
                'checked_in_at' => $isCheckedIn ? $startDateTime->copy()->addMinutes(5) : null,
                'started_at' => $isStarted ? $startDateTime->copy()->addMinutes(10) : null,
                'completed_at' => $isCompleted ? $endDateTime->copy()->subMinutes(5) : null,
                'cancelled_at' => $status === 'cancelled' ? $startDateTime->copy()->addMinutes(15) : null,
                'cancel_reason' => $status === 'cancelled' ? 'Khách báo bận và dời lịch.' : null,
                'override_conflict_reason' => in_array($index, [3, 16], true) ? 'Đã xác nhận ưu tiên theo lịch khách VIP.' : null,
                'notes' => $status === 'no_show' ? 'Đã gọi xác nhận nhưng khách không đến.' : ($package ? 'Thanh toán bằng gói liệu trình.' : null),
            ];

            $appointment = Appointment::query()->updateOrCreate(
                ['code' => sprintf('LH-DEMO-%03d', $index + 1)],
                $values,
            );

            $appointments->put($appointment->code, $appointment);
        }

        return $appointments;
    }

    private function seedPackageUsages(Collection $packages, Collection $appointments, Collection $employees, User $admin): void
    {
        foreach ($packages as $package) {
            $packageAppointments = $appointments
                ->filter(fn (Appointment $appointment): bool => $appointment->customer_package_id === $package->id)
                ->values();
            $usedSessions = min($package->sessions_used, $package->sessions_total);

            for ($session = 1; $session <= $usedSessions; $session++) {
                $appointment = $packageAppointments->get(($session - 1) % max(1, $packageAppointments->count()));
                $performedBy = $appointment?->primary_employee_id ?? $employees->values()->get(($session - 1) % max(1, $employees->count()))?->id;
                $usedAt = Carbon::parse($package->starts_on->toDateString())->addDays(($session - 1) * 5)->setTime(15, 0);

                PackageUsage::query()->updateOrCreate(
                    [
                        'customer_package_id' => $package->id,
                        'session_number' => $session,
                        'direction' => 'use',
                    ],
                    [
                        'appointment_id' => $appointment?->id,
                        'performed_by' => $performedBy,
                        'recorded_by' => $admin->id,
                        'verification_method' => 'admin_confirmation',
                        'verification_note' => 'Dữ liệu mẫu đã xác nhận tại quầy.',
                        'used_at' => $usedAt,
                    ],
                );
            }

            if ($package->code === 'THE-DEMO-002') {
                $restoreAppointment = $packageAppointments->get(0);
                $restoreUsedAt = Carbon::parse($package->starts_on->toDateString())->addDays(20)->setTime(17, 0);

                PackageUsage::query()->updateOrCreate(
                    [
                        'customer_package_id' => $package->id,
                        'session_number' => 5,
                        'direction' => 'use',
                    ],
                    [
                        'appointment_id' => $restoreAppointment?->id,
                        'performed_by' => $restoreAppointment?->primary_employee_id,
                        'recorded_by' => $admin->id,
                        'verification_method' => 'admin_confirmation',
                        'verification_note' => 'Ghi nhận dùng nhầm buổi thứ 5 trước khi hoàn tác.',
                        'used_at' => $restoreUsedAt,
                    ],
                );

                PackageUsage::query()->updateOrCreate(
                    [
                        'customer_package_id' => $package->id,
                        'session_number' => 5,
                        'direction' => 'restore',
                    ],
                    [
                        'appointment_id' => $restoreAppointment?->id,
                        'performed_by' => $restoreAppointment?->primary_employee_id,
                        'recorded_by' => $admin->id,
                        'verification_method' => 'admin_confirmation',
                        'verification_note' => 'Hoàn tác buổi dùng nhầm để minh họa lịch sử điều chỉnh.',
                        'used_at' => $restoreUsedAt->copy()->addDays(4)->setTime(17, 30),
                    ],
                );
            }
        }
    }

    private function seedInvoices(
        Collection $appointments,
        Collection $packages,
        Collection $offerings,
        Collection $employees,
        User $admin,
    ): Collection {
        $invoices = collect();
        $serviceIndex = 1;

        foreach ($appointments as $appointment) {
            if (! in_array($appointment->status, ['paid', 'completed_pending_payment'], true) || $appointment->payment_type !== 'service') {
                continue;
            }

            $offering = $offerings->firstWhere('id', $appointment->offering_id);

            if (! $offering) {
                continue;
            }

            $status = $appointment->status === 'paid' ? 'paid' : 'pending';
            $discount = $status === 'paid' && $serviceIndex % 4 === 0 ? 50000 : 0;
            $issuedAt = Carbon::parse($appointment->appointment_date->toDateString().' '.$appointment->start_time)->addMinutes(5);
            $method = $status === 'paid' ? ['cash', 'vietqr', 'pos'][$serviceIndex % 3] : null;
            $employee = $employees->firstWhere('id', $appointment->primary_employee_id);

            $invoice = $this->upsertInvoice(
                sprintf('HD-DEMO-SVC-%03d', $serviceIndex),
                $appointment->customer,
                $offering,
                $employee,
                $issuedAt,
                $status,
                $discount,
                $method,
                $admin,
                $appointment,
                null,
                $status === 'paid' ? 'Thu tiền dịch vụ tại quầy.' : 'Chờ thu tiền sau khi hoàn thành dịch vụ.',
            );
            $invoices->put($invoice->code, $invoice);
            $serviceIndex++;
        }

        $manualInvoices = [
            ['code' => 'HD-DEMO-VOID-001', 'customer' => 'KH-1003', 'offering' => 'DV002', 'status' => 'void', 'issued_at' => today()->copy()->subDays(12)->setTime(16, 20), 'discount' => 0, 'method' => null, 'note' => 'Hủy do khách đổi sang liệu trình khác.'],
            ['code' => 'HD-DEMO-VOID-002', 'customer' => 'KH-1016', 'offering' => 'DV005', 'status' => 'void', 'issued_at' => today()->copy()->subDays(9)->setTime(11, 10), 'discount' => 30000, 'method' => null, 'note' => 'Hủy do nhập nhầm dịch vụ.'],
            ['code' => 'HD-DEMO-PENDING-001', 'customer' => 'KH-1019', 'offering' => 'DV007', 'status' => 'pending', 'issued_at' => today()->copy()->setTime(14, 0), 'discount' => 0, 'method' => null, 'note' => 'Khách đang chờ xác nhận phương thức thanh toán.'],
        ];

        foreach ($manualInvoices as $manualInvoice) {
            $offering = $offerings[$manualInvoice['offering']];
            $employee = $employees->values()->get($offering->id % max(1, $employees->count()));
            $invoice = $this->upsertInvoice(
                $manualInvoice['code'],
                Customer::query()->where('code', $manualInvoice['customer'])->firstOrFail(),
                $offering,
                $employee,
                $manualInvoice['issued_at'],
                $manualInvoice['status'],
                $manualInvoice['discount'],
                $manualInvoice['method'],
                $admin,
                null,
                null,
                $manualInvoice['note'],
            );
            $invoices->put($invoice->code, $invoice);
        }

        $packageInvoices = [
            ['package' => 'THE-DEMO-001', 'issued_at' => today()->copy()->subMonth()->addDays(4)->setTime(10, 15), 'discount' => 300000, 'method' => 'vietqr'],
            ['package' => 'THE-DEMO-002', 'issued_at' => today()->copy()->subMonth()->addDays(12)->setTime(15, 30), 'discount' => 0, 'method' => 'pos'],
            ['package' => 'THE-DEMO-003', 'issued_at' => today()->copy()->subDays(18)->setTime(9, 0), 'discount' => 200000, 'method' => 'cash'],
            ['package' => 'THE-DEMO-004', 'issued_at' => today()->copy()->subDays(65)->setTime(17, 10), 'discount' => 250000, 'method' => 'vietqr'],
            ['package' => 'THE-DEMO-008', 'issued_at' => today()->copy()->subDays(25)->setTime(13, 40), 'discount' => 0, 'method' => 'cash'],
            ['package' => 'THE-DEMO-009', 'issued_at' => today()->copy()->subDays(450)->setTime(10, 45), 'discount' => 500000, 'method' => 'pos'],
        ];

        foreach ($packageInvoices as $index => $packageInvoice) {
            $package = $packages[$packageInvoice['package']];
            $offering = $offerings->firstWhere('id', $package->offering_id);
            $employee = $employees->values()->get(($index + 2) % max(1, $employees->count()));
            $invoice = $this->upsertInvoice(
                sprintf('HD-DEMO-PKG-%03d', $index + 1),
                $package->customer,
                $offering,
                $employee,
                $packageInvoice['issued_at'],
                'paid',
                $packageInvoice['discount'],
                $packageInvoice['method'],
                $admin,
                null,
                $package,
                'Bán gói liệu trình và kích hoạt thẻ cho khách hàng.',
            );
            $invoices->put($invoice->code, $invoice);
        }

        return $invoices;
    }

    private function upsertInvoice(
        string $code,
        Customer $customer,
        Offering $offering,
        ?Employee $employee,
        Carbon $issuedAt,
        string $status,
        int $discount,
        ?string $paymentMethod,
        User $admin,
        ?Appointment $appointment,
        ?CustomerPackage $package,
        string $notes,
    ): Invoice {
        $subtotal = (int) ($offering->price ?? 0);
        $netTotal = max(0, $subtotal - $discount);
        $paidAt = $status === 'paid' ? $issuedAt->copy()->addMinutes(3) : null;
        $voidedAt = $status === 'void' ? $issuedAt->copy()->addMinutes(2) : null;

        $invoice = Invoice::query()->updateOrCreate(
            ['code' => $code],
            [
                'customer_id' => $customer->id,
                'appointment_id' => $appointment?->id,
                'customer_package_id' => $package?->id,
                'created_by' => $admin->id,
                'type' => $package ? 'package' : 'service',
                'status' => $status,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_received' => $status === 'paid' ? $netTotal : 0,
                'payment_method' => $paymentMethod,
                'issued_at' => $issuedAt,
                'paid_at' => $paidAt,
                'voided_at' => $voidedAt,
                'notes' => $notes,
            ],
        );

        InvoiceItem::query()->updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'offering_id' => $offering->id,
                'employee_id' => $employee?->id,
                'description' => $offering->name,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'line_total' => $subtotal,
                'package_sessions' => $package ? $offering->sessions_total : 0,
            ],
        );

        if ($status === 'paid') {
            Payment::query()->updateOrCreate(
                ['receipt_code' => 'PT-'.$code],
                [
                    'invoice_id' => $invoice->id,
                    'received_by' => $admin->id,
                    'method' => $paymentMethod ?: 'cash',
                    'amount' => $netTotal,
                    'status' => 'completed',
                    'reference' => in_array($paymentMethod, ['vietqr', 'pos'], true) ? 'DEMO-'.$code : null,
                    'paid_at' => $paidAt,
                ],
            );
        }

        return $invoice->fresh();
    }

    private function seedCommissionEntries(Collection $invoices): void
    {
        foreach ($invoices->where('status', 'paid') as $invoice) {
            $invoice->loadMissing(['appointment.primaryEmployee', 'appointment.supportEmployee', 'items.offering', 'items.employee', 'customerPackage.offering']);
            $offering = $invoice->items->first()?->offering ?? $invoice->customerPackage?->offering;

            if (! $offering) {
                continue;
            }

            $occurredAt = $invoice->paid_at ?? $invoice->issued_at ?? now();
            $rules = CommissionRule::query()
                ->where('offering_id', $offering->id)
                ->where('basis', 'payment')
                ->where('is_active', true)
                ->whereDate('starts_on', '<=', $occurredAt->toDateString())
                ->where(function ($query) use ($occurredAt): void {
                    $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $occurredAt->toDateString());
                })
                ->get()
                ->sortBy(fn (CommissionRule $rule): int => match ($rule->beneficiary_type) {
                    'specific_employee' => 0,
                    'primary_technician' => 1,
                    'support_technician' => 2,
                    default => 3,
                })
                ->values();

            $rule = null;
            $employee = null;

            foreach ($rules as $candidate) {
                $candidateEmployee = match ($candidate->beneficiary_type) {
                    'specific_employee' => $candidate->beneficiaryEmployee,
                    'support_technician' => $invoice->appointment?->supportEmployee,
                    default => $invoice->appointment?->primaryEmployee ?? $invoice->items->first()?->employee,
                };

                if ($candidateEmployee) {
                    $rule = $candidate;
                    $employee = $candidateEmployee;
                    break;
                }
            }

            if (! $rule || ! $employee) {
                continue;
            }

            $basisAmount = (int) ($invoice->total_received ?: max(0, $invoice->subtotal - $invoice->discount_amount));
            $amount = $rule->calculation_type === 'fixed'
                ? (int) round((float) $rule->value)
                : (int) round($basisAmount * ((float) $rule->value / 100));
            $entryValues = [
                'employee_id' => $employee->id,
                'invoice_id' => $invoice->id,
                'appointment_id' => $invoice->appointment_id,
                'offering_id' => $offering->id,
                'commission_rule_id' => $rule->id,
                'basis' => 'payment',
                'calculation_type' => $rule->calculation_type,
                'rate' => $rule->value,
                'amount' => $amount,
                'status' => $occurredAt->lt(today()->startOfMonth()) ? 'locked' : 'pending',
                'occurred_at' => $occurredAt,
            ];
            $entry = CommissionEntry::query()
                ->where('invoice_id', $invoice->id)
                ->where('employee_id', $employee->id)
                ->where('commission_rule_id', $rule->id)
                ->first();

            if ($entry) {
                $entry->update($entryValues);
            } else {
                CommissionEntry::query()->create($entryValues);
            }
        }
    }

    private function seedPayroll(Collection $employees, User $admin): void
    {
        $currentStart = today()->startOfMonth();
        $currentEnd = today()->endOfMonth();
        $previousStart = $currentStart->copy()->subMonth()->startOfMonth();
        $previousEnd = $currentStart->copy()->subDay();

        $currentPeriod = PayrollPeriod::query()->updateOrCreate(
            ['code' => 'BL-DEMO-'.$currentStart->format('Ym')],
            [
                'period_start' => $currentStart->toDateString(),
                'period_end' => $currentEnd->toDateString(),
                'status' => 'draft',
                'locked_by' => null,
                'locked_at' => null,
            ],
        );

        $previousPeriod = PayrollPeriod::query()->updateOrCreate(
            ['code' => 'BL-DEMO-'.$previousStart->format('Ym')],
            [
                'period_start' => $previousStart->toDateString(),
                'period_end' => $previousEnd->toDateString(),
                'status' => 'locked',
                'locked_by' => $admin->id,
                'locked_at' => $previousEnd->copy()->setTime(18, 0),
            ],
        );

        $adjustments = [
            ['code' => 'DC-DEMO-001', 'period' => $currentPeriod, 'employee' => 'NV007', 'type' => 'bonus', 'amount' => 250000, 'status' => 'pending', 'reason' => 'Thưởng doanh số tuần đầu tháng.'],
            ['code' => 'DC-DEMO-002', 'period' => $currentPeriod, 'employee' => 'NV008', 'type' => 'bonus', 'amount' => 500000, 'status' => 'approved', 'reason' => 'Thưởng khách hàng quay lại theo chương trình chăm sóc da.', 'reviewed' => true],
            ['code' => 'DC-DEMO-003', 'period' => $currentPeriod, 'employee' => 'NV009', 'type' => 'deduction', 'amount' => 150000, 'status' => 'rejected', 'reason' => 'Đề nghị khấu trừ do đi muộn; quản lý không duyệt.', 'reviewed' => true],
            ['code' => 'DC-DEMO-004', 'period' => $previousPeriod, 'employee' => 'NV010', 'type' => 'bonus', 'amount' => 300000, 'status' => 'approved', 'reason' => 'Thưởng hoàn thành chỉ tiêu liệu trình tháng trước.', 'reviewed' => true],
        ];

        foreach ($adjustments as $adjustment) {
            PayrollAdjustment::query()->updateOrCreate(
                ['code' => $adjustment['code']],
                [
                    'payroll_period_id' => $adjustment['period']->id,
                    'employee_id' => $employees[$adjustment['employee']]->id,
                    'created_by' => $admin->id,
                    'reviewed_by' => ($adjustment['reviewed'] ?? false) ? $admin->id : null,
                    'type' => $adjustment['type'],
                    'amount' => $adjustment['amount'],
                    'status' => $adjustment['status'],
                    'reason' => $adjustment['reason'],
                    'reviewed_at' => ($adjustment['reviewed'] ?? false) ? now()->subDays(1) : null,
                ],
            );
        }

        $this->refreshPayrollPeriod($currentPeriod, $employees);
        $this->refreshPayrollPeriod($previousPeriod, $employees);
    }

    private function refreshPayrollPeriod(PayrollPeriod $period, Collection $employees): void
    {
        $periodStart = Carbon::parse($period->period_start->toDateString());
        $periodEnd = Carbon::parse($period->period_end->toDateString());
        $isHistorical = $periodStart->lt(today()->startOfMonth());
        $approvedAdjustments = PayrollAdjustment::query()
            ->where('payroll_period_id', $period->id)
            ->where('status', 'approved')
            ->get()
            ->groupBy('employee_id');
        $totalBase = 0;
        $totalCommission = 0;
        $totalNet = 0;

        foreach ($employees->values() as $index => $employee) {
            $commission = (int) CommissionEntry::query()
                ->where('employee_id', $employee->id)
                ->whereDate('occurred_at', '>=', $periodStart->toDateString())
                ->whereDate('occurred_at', '<=', $periodEnd->toDateString())
                ->sum('amount');
            $workDays = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', '>=', $periodStart->toDateString())
                ->whereDate('attendance_date', '<=', $periodEnd->toDateString())
                ->whereIn('status', ['present', 'late'])
                ->count();

            if ($isHistorical && $workDays === 0) {
                $workDays = 24;
            }

            $tips = $isHistorical ? 0 : ($index % 4 === 0 ? 100000 : 0);
            $deductions = 0;
            $adjustmentTotal = $approvedAdjustments->get($employee->id, collect())->sum(function (PayrollAdjustment $adjustment): int {
                return $adjustment->type === 'deduction' ? -$adjustment->amount : $adjustment->amount;
            });
            $baseSalary = (int) $employee->base_salary;
            $netPay = max(0, $baseSalary + $commission + $tips - $deductions + $adjustmentTotal);

            PayrollLine::query()->updateOrCreate(
                ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                [
                    'base_salary' => $baseSalary,
                    'work_days' => $workDays,
                    'commission_amount' => $commission,
                    'tips' => $tips,
                    'deductions' => $deductions,
                    'net_pay' => $netPay,
                    'status' => $period->status === 'locked' ? 'locked' : 'draft',
                ],
            );

            $totalBase += $baseSalary;
            $totalCommission += $commission;
            $totalNet += $netPay;
        }

        $period->update([
            'total_base_salary' => $totalBase,
            'total_commission' => $totalCommission,
            'total_net_pay' => $totalNet,
        ]);
    }

    private function refreshDemoCustomerTotals(Collection $customers): void
    {
        foreach ($customers->filter(fn (Customer $customer): bool => str_starts_with($customer->code, 'KH-10')) as $customer) {
            $paidInvoices = Invoice::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'paid')
                ->get(['total_received', 'paid_at']);
            $latestPaidAt = $paidInvoices
                ->filter(fn (Invoice $invoice): bool => $invoice->paid_at !== null)
                ->sortByDesc(fn (Invoice $invoice): int => $invoice->paid_at->getTimestamp())
                ->first()?->paid_at;

            $customer->update([
                'total_spend' => (int) $paidInvoices->sum('total_received'),
                'last_visit_at' => $latestPaidAt,
            ]);
        }
    }
}
