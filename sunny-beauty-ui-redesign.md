# Khắc phục giao diện chung chung của Sunny Beauty

## Mục tiêu

Biến giao diện hiện tại thành bàn điều hành spa theo nghiệp vụ, vẫn giữ nguyên route, dữ liệu, quyền, validation và workflow.

## Công việc

- [x] Sửa rule định tuyến UI để nhận diện Laravel Blade/Livewire trước khi áp dụng hướng dẫn framework.
- [x] Giới hạn các rule thiết kế thử nghiệm đối với dashboard và màn hình dữ liệu nội bộ.
- [x] Mở rộng `DESIGN.md` với actor, workflow, mật độ, trạng thái và mẫu bố cục theo từng module.
- [x] Tổ chức lại navigation theo nhóm công việc hằng ngày, giao dịch và đội ngũ.
- [x] Chuyển shell, heading, card, bảng và trạng thái sang token ngữ nghĩa thống nhất.
- [x] Tạo khác biệt bố cục giữa tổng quan, lịch hẹn, phòng, CRM và các workbench tài chính.
- [x] Kiểm tra Blade, build frontend, test Laravel và bộ kiểm tra AG Kit.

## Hoàn tất khi

- [x] AI không còn bị ép dùng tư duy React/Next.js hoặc layout thử nghiệm cho Blade/Livewire.
- [x] `DESIGN.md` đủ thông tin để thiết kế từng module mà không tự suy diễn từ template.
- [x] Các màn hình chính có ngôn ngữ nghiệp vụ riêng nhưng vẫn cùng một hệ thống thị giác.
- [x] Không thay đổi hành vi nghiệp vụ và toàn bộ kiểm tra liên quan đều đạt.

## Ranh giới

- Không thay đổi database, route, API, quyền, validation hoặc logic Livewire.
- Không thêm dependency giao diện mới.
- Không xóa file mẫu hoặc file scaffold trong phạm vi lần sửa này.
