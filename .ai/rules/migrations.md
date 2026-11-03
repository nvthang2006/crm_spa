---
paths:
  - 'supabase/migrations/**'
---

# Migrations

## Ghi nhận ca và kỳ lương dùng cùng khóa giao dịch
record_daily_shift và RPC payroll dùng pg_advisory_xact_lock(20261102,1). Chặn ca vào ngày thuộc kỳ đã khóa; payroll tính theo khoảng ngày Việt Nam với cận trên loại trừ, chốt phải tính lại snapshot trước khóa. Không áp toàn bộ migration cũ lên remote: schema nền đã được nhập trước lịch sử migration; chỉ áp migration mới đã kiểm tra.
