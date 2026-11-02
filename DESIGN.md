---
name: Sunny Beauty Operations Design System
version: 2.1.0
description: Hệ thống thiết kế cho bàn điều hành spa Sunny Beauty bằng Laravel Blade và Livewire.
colors:
  brand-primary: "#0F766E"
  brand-strong: "#0B5F59"
  brand-soft: "#E7F2EF"
  brand-subtle: "#F2F7F5"
  canvas: "#F6F7F8"
  surface: "#FFFFFF"
  surface-muted: "#F8F9FA"
  sidebar: "#1F3031"
  sidebar-muted: "#B1C0C0"
  border: "#E1E5E7"
  border-strong: "#C8D0D3"
  text-primary: "#202B30"
  text-secondary: "#46565E"
  text-muted: "#606F75"
  focus: "#0F766E"
  info: "#4B606A"
  success: "#346C4B"
  warning: "#8A5C20"
  danger: "#AC3636"
typography:
  display:
    fontFamily: "Be Vietnam Pro"
    fontSize: "26px"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.02em"
  heading:
    fontFamily: "Be Vietnam Pro"
    fontSize: "16px"
    fontWeight: 700
    lineHeight: 1.35
  body:
    fontFamily: "Be Vietnam Pro"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Be Vietnam Pro"
    fontSize: "12px"
    fontWeight: 600
    lineHeight: 1.35
  metric:
    fontFamily: "Be Vietnam Pro"
    fontSize: "24px"
    fontWeight: 700
    lineHeight: 1.1
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  2xl: "32px"
rounded:
  control: "8px"
  surface: "10px"
  pill: "9999px"
elevation:
  surface: "0 1px 2px rgba(15, 45, 38, 0.04)"
  overlay: "0 18px 45px rgba(15, 45, 38, 0.14)"
components:
  sidebar:
    backgroundColor: "{colors.sidebar}"
    textColor: "{colors.sidebar-muted}"
    width: "260px"
  button-primary:
    backgroundColor: "{colors.brand-primary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.control}"
    height: "36px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.control}"
    height: "38px"
  workbench:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.surface}"
    padding: "16px"
  status-strip:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.surface}"
    padding: "16px"
  pagination:
    height: "34px"
    rounded: "{rounded.control}"
  select:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "10px"
    popoverRounded: "12px"
    height: "38px"
    focusBorder: "{colors.brand-primary}"
---

# Sunny Beauty Operations Design System

## Overview

Sunny Beauty là hệ thống vận hành nội bộ cho quản lý, lễ tân và nhân sự spa. Bản sắc không đến từ hiệu ứng trang trí mà từ cách giao diện phản ánh đúng nhịp làm việc: lịch hẹn cần điều phối nhanh, phòng cần nhìn trạng thái ngay, CRM cần xem lịch sử theo ngữ cảnh và tài chính cần đối soát chính xác.

**Design read:** bàn điều hành spa điềm tĩnh, tin cậy và giàu trạng thái. `DESIGN_VARIANCE: 3`, `MOTION_INTENSITY: 2`, `VISUAL_DENSITY: 8`.

### Người dùng và ưu tiên

1. **Lễ tân:** tạo lịch, tiếp đón, phân KTV/phòng, thu tiền và xử lý ngoại lệ.
2. **Quản lý:** theo dõi nhịp vận hành, doanh thu, cảnh báo, hoa hồng và kỳ lương.
3. **Nhân sự vận hành:** nhận biết lịch, phòng, trạng thái dịch vụ và việc cần xử lý.

Một màn hình tốt phải trả lời nhanh: việc gì đang diễn ra, việc gì cần xử lý tiếp theo và hành động nào có rủi ro.

## Colors

- Teal `brand-primary` là màu hành động và nhận diện Sunny Beauty, không dùng để tô mọi thành phần.
- `canvas`, `surface` và `surface-muted` tạo phân cấp bằng sắc độ và đường viền, hạn chế phụ thuộc vào shadow.
- Màu `success`, `warning` và `danger` chỉ dùng cho trạng thái có ý nghĩa nghiệp vụ; `info` dùng xám xanh trung tính.
- Nền trang trung tính, bảng và thẻ thống kê nền trắng. Số liệu và icon thống kê dùng màu chữ trung tính, không tô màu theo vị trí hoặc loại số liệu.
- Teal dành cho hành động chính, lựa chọn và focus. Badge dùng nền nhạt; màu trạng thái tập trung ở nhãn và biểu tượng, không phủ cả vùng dữ liệu.
- Màu dùng qua biến `--spa-*`; component không ép mã màu riêng. Nền trạng thái: success `#F0F6F2`, warning `#FAF5EB`, danger `#FCF1F1`, info `#F2F5F6`.
- Màu không được là tín hiệu duy nhất; trạng thái luôn đi kèm nhãn hoặc biểu tượng.
- Không thay brand teal bằng hồng, tím hoặc màu xu hướng khi chưa có quyết định đổi thương hiệu.

## Typography

- Dùng **Be Vietnam Pro** cho toàn hệ thống để giữ tiếng Việt rõ ràng ở mật độ cao.
- Số liệu quan trọng dùng vai trò `metric`; số tiền và số lượng phải dễ quét nhưng không lấn át hành động.
- Tiêu đề trang tối đa 26px. Đây là công cụ vận hành, không dùng headline kiểu landing page.
- Material Symbols Outlined là bộ icon duy nhất; icon trang trí cạnh chữ phải có `aria-hidden="true"`.

## Layout

### App shell

- Sidebar desktop rộng 260px, chia navigation theo nhóm **Trong ngày**, **Khách hàng & giao dịch**, **Đội ngũ**.
- Header chứa tìm kiếm toàn cục, trạng thái phiên vận hành, thông báo và tài khoản.
- Nội dung tối đa 1480px với gutter 32px desktop, 24px tablet và 14px mobile.

### Mẫu bố cục theo module

- **Tổng quan:** command center. Ưu tiên cảnh báo, lịch tiếp theo và khả năng phục vụ; số liệu tài chính là ngữ cảnh, không phải bốn card giống nhau.
- **Lịch hẹn:** schedule workbench. Ngày, trạng thái và hành động chuyển bước phải nằm trong cùng một vùng quét.
- **Phòng & giường:** spatial status board. Màu nền, nhãn và hành động phản ánh trạng thái vật lý của tài nguyên.
- **Khách hàng:** master-detail. Danh sách phục vụ tìm kiếm; hồ sơ mở ra để xem sức khỏe, thẻ và lịch sử trong một ngữ cảnh.
- **Dịch vụ, hóa đơn, nhân viên, bảng lương:** data workbench. Toolbar, bảng dữ liệu và panel xử lý có thứ bậc rõ; hành động nguy hiểm tách khỏi hành động chính.

Không dùng cùng một cụm `heading + 4 stat cards + table` cho mọi module.

### Responsive

- Dưới 1100px: status strip chuyển hai cột; workbench nhiều vùng chuyển một cột.
- Dưới 760px: navigation thành thanh cuộn theo nhóm, heading và action xếp dọc, bảng được cuộn ngang có chủ ý.
- Không ẩn hành động nghiệp vụ chính trên mobile. Nút icon-only phải có vùng bấm ít nhất 40px và nhãn truy cập.

## Elevation & Depth

- Dùng border và tonal surface làm phân cấp chính.
- Shadow nhẹ chỉ dành cho overlay, dropdown và surface thật sự nổi.
- Không dùng glassmorphism, glow, parallax hoặc decorative gradient cho màn hình vận hành.

## Shapes

- Control: 8px.
- Workbench/card: 10px.
- Badge trạng thái: pill.
- Không trộn bo góc tùy ý giữa các module.

## Components

### Page heading

Hiển thị ngữ cảnh module, tiêu đề, mô tả ngắn và hành động chính. Mỗi trang chỉ có một hành động primary rõ ràng.

### Status strip

Nhóm các số liệu liên quan thành một dải thống nhất thay vì nhiều card rời. Nhãn phải mô tả trạng thái hoặc khối lượng công việc, không dùng số liệu trang trí.

### Workbench

Gồm toolbar lọc, nội dung chính và vùng hành động. Form mở trong cùng ngữ cảnh phải có tiêu đề, mô tả rủi ro và nút hủy/lưu rõ ràng.

### Table

- Header dễ quét, hàng có hover nhẹ, cột hành động nằm cuối.
- Số tiền căn và định dạng nhất quán.
- Bảng dài phải phân trang; empty state nói rõ dữ liệu nào chưa có.

### State badge

Badge dùng nhãn tiếng Việt đầy đủ. Không dùng chấm màu trang trí; biểu tượng chỉ xuất hiện khi giúp phân biệt trạng thái.

### Dropdown & Select (`x-ui.select`)

- **Trigger**: Chiều cao 38px, bo góc 10px, viền chuyển tiếp mượt mà, hỗ trợ icon đại diện (prefix icon) tăng tính trực quan.
- **Floating Popover**: Nền mờ `bg-white/98 backdrop-blur-md`, bo góc 12px, shadow sâu `shadow-xl`, viền mảnh.
- **Rich Options**: Tùy chọn hỗ trợ icon, subtitle/mô tả phụ và badge nhãn để thể hiện rõ nét thông tin (hạng thẻ, thời lượng, giá, kỹ thuật viên).
- **Tính năng nâng cao**: Hỗ trợ tìm kiếm tức thì (`searchable`), nút xóa nhanh (`clearable`), hoàn toàn tương thích Livewire và form submission.

### Motion

Chỉ dùng transition 120-180ms cho hover, focus, mở/đóng và phản hồi trạng thái. Tôn trọng `prefers-reduced-motion`. Không dùng scroll reveal trong admin.

## Do's and Don'ts

- Làm nổi bật bước tiếp theo của workflow và cảnh báo có thể hành động.
- Dùng cấu trúc riêng cho lịch, phòng, CRM và tài chính.
- Giữ route, API, quyền, validation và logic Livewire khi chỉ redesign UI.
- Dùng token ngữ nghĩa thay cho hex rải rác trong view.
- Không sinh nội dung mẫu, số liệu giả hoặc testimonial cho dashboard.
- Không áp dụng React/Next.js pattern vào Blade/Livewire.
- Không ép phong cách radical, hero marketing hoặc animation trình diễn vào giao diện nội bộ.
