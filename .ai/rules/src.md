---
paths:
  - 'desktop/src/**'
---

# Src

## Nhập ca lưu snapshot hoa hồng theo từng giao dịch
Nhập cuối ngày gọi record_daily_shift một lần cho mỗi ca, giữ UUID và payload khi thử lại. Mức riêng chỉ thay khoản nhân viên thực hiện, vẫn giữ khoản specific_employee độc lập; không sửa quy tắc chung. Notes [Ca gói]/[Ca lẻ] là dấu phân loại cho nhật ký và Tổng quan; dữ liệu cũ dùng fallback. Ca gói không thu thêm tính % theo giá niêm yết mỗi buổi, không tạo thẻ mới.
