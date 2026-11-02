---
paths:
  - app/Services/DailyRevenueService.php
  - app/Services/CommissionRuleService.php
  - app/Services/RevenueReportService.php
---

# Services

## Không gộp phiếu nhập cuối ngày khi chưa sửa phân bổ hoa hồng
Mỗi dòng dịch vụ cuối ngày dùng một phiếu thu riêng vì CheckoutService hiện phân bổ tiền theo số lượng và chống trùng hoa hồng theo invoice, nhân viên, quy tắc. Nếu gộp nhiều dịch vụ vào một phiếu, phải sửa và kiểm thử cả phân bổ theo tiền thực thu từng dòng lẫn hoa hồng cho dịch vụ lặp; tránh mất hoặc chia sai hoa hồng.

## Mức riêng theo người thực hiện thay mức KTV chính
beneficiary_type=performer_employee chỉ áp dụng khi beneficiary_employee_id trùng nhân viên thực hiện. Mức riêng còn hiệu lực thay mọi quy tắc KTV chính của dịch vụ; specific_employee vẫn là khoản cho người được chỉ định, không phải điều kiện người thực hiện. Dùng cùng bộ chọn quy tắc cho xem trước và lưu giao dịch.

## Báo cáo doanh thu theo tiền thực thu và hoa hồng đã lưu
Doanh thu ngày/tháng lấy phiếu paid theo paid_at và total_received, kể cả phiếu bán gói; loại phiếu refunded. Hoa hồng lấy amount/rate đã lưu, trạng thái pending hoặc locked theo occurred_at, không tính lại từ cấu hình hiện tại. Bộ lọc chi tiết không thay tổng toàn kỳ. Phiếu cũ lặp dịch vụ không có liên kết hoa hồng đến từng dòng: hiển thị khoản đó một lần ở dòng đầu cùng offering.
