---
paths:
  - 'app/Services/AppointmentService.php, app/Services/OperationalAlertService.php, app/Livewire/Admin/AppointmentsPage.php, app/Livewire/Admin/DashboardPage.php, resources/views/livewire/admin/appointments-page.blade.php, resources/views/livewire/admin/dashboard-page.blade.php, resources/views/layouts/admin.blade.php'
---

# Layouts

## Mô hình nhỏ không quản lý phòng giường mặc định
spa.resource_management_enabled mặc định false. Khi tắt, ẩn phòng/giường ở menu, tổng quan và lịch hẹn; bỏ xung đột và trạng thái tài nguyên nhưng giữ kiểm tra trùng giờ nhân viên chính/phụ. Lịch mới không gắn tài nguyên; sửa lịch cũ giữ nguyên service_resource_id; không thay đổi trạng thái tài nguyên khi chuyển lịch. Cảnh báo tài nguyên cũ chỉ được vô hiệu hóa, không xóa lịch sử. Timeline nhóm theo ID nhân viên, không theo tên. Module và dữ liệu tài nguyên được giữ để có thể bật lại.
