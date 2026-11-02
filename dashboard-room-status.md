# Kế hoạch thiết kế lại khu vực Trạng thái phòng trên Dashboard

**File:** `dashboard-room-status.md`  
**Dự án:** Sunny Beauty / An Miên Spa & Wellness  
**Mục tiêu:** Cải tạo khu vực "Trạng thái phòng nhanh" trên trang Tổng quan vận hành, loại bỏ hoàn toàn thiết kế AI thô cứng, nâng cấp lên chuẩn nhận diện Spa cao cấp bám sát hợp đồng thiết kế Stitch UI.

---

## 1. Phân tích thực trạng & Lý do cần đổi mới

- **Vấn đề hiện tại:**
  - 4 khối trạng thái phòng ban đầu là 4 hộp `div` viền xám trơn (`1px solid #e2e8f0`), bên trong gắn nhãn badge con nhộng và một con số đen đơn điệu (`4`, `11`, `3`, `2`).
  - Thiếu tính trực quan về sơ đồ phòng/giường thực tế theo phân khu của spa.
  - Người quản lý khi nhìn vào dashboard không nắm được ngay giường nào đang có khách, giường nào trống sạch hay phòng nào đang chờ dọn.
- **Nguồn tham chiếu bắt buộc:**
  - [dashboard.html](file:///home/nvthang2006/Desktop/sunny_beauty/.stitch/designs/dashboard.html#L302-L380) trong thư mục Stitch UI.
  - [DESIGN.md](file:///home/nvthang2006/Desktop/sunny_beauty/.stitch/DESIGN.md) (token màu, font Be Vietnam Pro, bảng màu trạng thái).

---

## 2. Đề xuất 2 phương án thiết kế (Socratic Choices)

### Phương án 1 (Khuyên dùng - Chuẩn 100% Stitch UI Reference): Sơ đồ phòng & giường mini (Mini Room Grid)
*Đúng theo bản thiết kế mẫu [dashboard.html](file:///home/nvthang2006/Desktop/sunny_beauty/.stitch/designs/dashboard.html#L302-L380)*:
1. **Dòng chú thích trạng thái (Status Legend Bar)** ở trên cùng:
   - `Đang dùng (X)` (chấm tròn xanh ngọc `#0F766E`)
   - `Trống (X)` (chấm tròn xanh lá `#16A34A` / xám)
   - `Chờ dọn (X)` (chấm tròn vàng hổ phách `#D97706`)
   - `Bảo trì (X)` (chấm tròn đỏ `#DC2626`)
2. **Khối danh sách phân khu & giường thực tế (Mini Room List)**:
   - Hiển thị danh sách các phân khu phòng (vd: *Phòng VIP 1 - Tầng 2*, *Phòng VIP 2*, *Phòng Chăm Sóc Da*, *Phòng Massage Body*...).
   - Bên cạnh mỗi phòng là các ô vuông giường nhỏ xinh xắn (`1A`, `1B`, `D1`, `D2`, `B1`, `B2`...) được tô màu trực quan theo trạng thái thực tế.
   - Hover vào từng giường có tooltip chi tiết: tên giường, trạng thái, hoặc mã lịch hẹn đang phục vụ.
3. **Ưu điểm**: Cực kỳ trực quan, phản ánh chân thực không gian phòng ốc thực tế của spa, đúng 100% bản vẽ của Stitch UI.

### Phương án 2: Thẻ KPI trạng thái vận hành hiện đại (Modern Operations KPI Cards)
*Nếu người dùng chỉ muốn xem số liệu tổng quan dạng chỉ số đếm thay vì sơ đồ phân khu*:
1. 4 thẻ widget được thiết kế theo phong cách Apple/Stripe Dashboard:
   - Icon Material Symbols bo tròn mềm mại.
   - Con số to rõ nét (24px, 800) kèm phụ đề ngữ cảnh thực tế (*Đang có khách*, *Sẵn sàng đón*, *Cần thay drap*, *Tạm dừng*).
   - Thanh mini progress bar đo tỷ lệ phần trăm trên tổng số giường.
2. **Ưu điểm**: Nhỏ gọn, tập trung nhanh vào các con số tổng.

---

## 3. Phân rã nhiệm vụ (Task Breakdown)

| Task ID | Nội dung | Chuyên viên (Agent) | Kỹ năng (Skills) | File tác động | Tiêu chí nghiệm thu (Verification) |
|---|---|---|---|---|---|
| **TASK-01** | Chuẩn bị dữ liệu phân khu & tài nguyên theo chuẩn Stitch | `backend-specialist` | `laravel-best-practices` | `app/Livewire/Admin/DashboardPage.php` | Tải quan hệ `$areas = ResourceArea::with('resources')->get()` và tính toán thống kê trạng thái |
| **TASK-02** | Xây dựng CSS phong cách Mini Room & Status Dot | `frontend-specialist` | `frontend-design`, `clean-code` | `resources/css/app.css` | Class `.spa-mini-room`, `.spa-bed-tag`, `.spa-legend`, hiệu ứng hover và màu chuẩn Stitch |
| **TASK-03** | Cập nhật cấu trúc Blade cho card Trạng thái phòng nhanh | `frontend-specialist` | `frontend-design` | `resources/views/livewire/admin/dashboard-page.blade.php` | Render đúng sơ đồ mini room và thanh legend theo phương án đã chọn |
| **TASK-04** | Tinh chỉnh Status Badge toàn hệ thống | `frontend-specialist` | `frontend-design` | `resources/css/app.css` | Màu `spa-badge-info` thành xanh ngọc `#0f766e`, có viền border mờ |
| **TASK-05** | Kiểm thử & Xác minh toàn diện (Phase X) | `test-engineer` | `verify-changes`, `lint-and-validate` | Toàn dự án | `npm run build` không lỗi, `php artisan test` 100% Passed |

---

## 4. Kế hoạch kiểm thử & nghiệm thu (Phase X)

- [ ] 1. Kiểm tra biên dịch tài nguyên frontend: `npm run build`
- [ ] 2. Kiểm tra bộ test tự động: `php artisan test --compact`
- [ ] 3. Kiểm tra hiển thị responsive trên desktop (>= 1200px) và mobile/tablet
- [ ] 4. Đối chiếu trực quan với màn hình mẫu `.stitch/designs/dashboard.html`
- [ ] 5. Đảm bảo ngôn ngữ tiếng Việt có dấu 100% chuẩn quy tắc
