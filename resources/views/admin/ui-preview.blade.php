@component('layouts.admin', ['title' => 'Xem trước Blade UI Components · Sunny Beauty'])
    <div class="spa-page-heading">
        <div>
            <h1>Bộ Blade UI Component Nền Tảng</h1>
            <p>Trang kiểm tra và xem trước toàn diện các biến thể, kích cỡ, trạng thái và khả năng tiếp cận (Accessibility) theo chuẩn Stitch.</p>
        </div>
        <div class="spa-actions">
            <x-ui.button variant="secondary" icon="refresh" onclick="window.location.reload()">Làm mới</x-ui.button>
            <x-ui.button variant="primary" icon="check_circle" onclick="alert('Component hoạt động bình thường!')">Xác nhận tương tác</x-ui.button>
        </div>
    </div>

    <!-- KHU VỰC 1: NÚT BẤM (x-ui.button) -->
    <section class="spa-card" style="margin-bottom: 24px;">
        <div class="spa-card-header">
            <div>
                <h2>1. Nút Bấm (x-ui.button)</h2>
                <p>Hỗ trợ biến thể primary, secondary, danger, ghost; kích thước sm, md, lg; icon; link <a> và Livewire loading.</p>
            </div>
            <x-ui.badge variant="success">Hoàn thành</x-ui.badge>
        </div>
        <div class="spa-card-body">
            <div class="space-y-4">
                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Các biến thể (Variants - Size MD)</h4>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button variant="primary" icon="add">Primary Action</x-ui.button>
                        <x-ui.button variant="secondary" icon="edit">Secondary Action</x-ui.button>
                        <x-ui.button variant="danger" icon="delete">Danger Action</x-ui.button>
                        <x-ui.button variant="ghost" icon="visibility">Ghost Action</x-ui.button>
                        <x-ui.button variant="primary" href="#test-link" icon="open_in_new">Thẻ Link (a[href])</x-ui.button>
                        <x-ui.button variant="secondary" disabled icon="block">Disabled</x-ui.button>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Các kích thước (Sizes)</h4>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button variant="primary" size="sm" icon="add">Nút Nhỏ (sm)</x-ui.button>
                        <x-ui.button variant="primary" size="md" icon="add">Nút Vừa (md - mặc định)</x-ui.button>
                        <x-ui.button variant="primary" size="lg" icon="add">Nút Lớn (lg)</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- KHU VỰC 2: FORM CONTROLS (x-ui.input, x-ui.select, x-ui.textarea, x-ui.checkbox, x-ui.radio) -->
    <section class="spa-card" style="margin-bottom: 24px;">
        <div class="spa-card-header">
            <div>
                <h2>2. Bộ Điều Khiển Form (x-ui.input, x-ui.select, x-ui.textarea, x-ui.checkbox, x-ui.radio)</h2>
                <p>Kiểm tra toàn diện các loại input, textarea, select, checkbox, radio, các trạng thái validation, prefix/suffix, focus-visible và responsive.</p>
            </div>
            <x-ui.badge variant="success">Hoàn thành</x-ui.badge>
        </div>
        <div class="spa-card-body space-y-8">
            <!-- 2.1 CÁC KIỂU INPUT VÀ PREFIX/SUFFIX -->
            <div>
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">2.1 Các Kiểu Ô Nhập (Input Types & Prefix/Suffix)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <x-ui.input
                        label="Họ và tên khách hàng (Text)"
                        name="customer_name"
                        placeholder="Nhập họ và tên..."
                    />

                    <x-ui.input
                        label="Địa chỉ email (Email)"
                        name="email"
                        type="email"
                        prefix="mail"
                        placeholder="example@sunnybeauty.vn"
                    />

                    <x-ui.input
                        label="Số điện thoại liên hệ (Tel)"
                        name="phone"
                        type="tel"
                        prefix="call"
                        placeholder="0912 345 678"
                    />

                    <x-ui.input
                        label="Mật khẩu tài khoản (Password)"
                        name="password"
                        type="password"
                        prefix="lock"
                        placeholder="••••••••"
                    />

                    <x-ui.input
                        label="Tìm kiếm nhanh (Search)"
                        name="search_query"
                        type="search"
                        prefix="search"
                        placeholder="Tìm khách hàng, dịch vụ..."
                    />

                    <x-ui.input
                        label="Số tiền thanh toán (Number & Suffix)"
                        name="amount"
                        type="number"
                        value="1200000"
                        suffix="₫"
                    />

                    <x-ui.input
                        label="Ngày thực hiện (Date)"
                        name="appointment_date"
                        type="date"
                        value="2026-09-09"
                    />

                    <x-ui.input
                        label="Khung giờ phục vụ (Time)"
                        name="appointment_time"
                        type="time"
                        value="14:30"
                    />

                    <x-ui.input
                        label="Thời gian hẹn đầy đủ (Datetime-local)"
                        name="appointment_datetime"
                        type="datetime-local"
                        value="2026-09-09T14:30"
                    />
                </div>
            </div>

            <!-- 2.2 CÁC TRẠNG THÁI VÀ RÀNG BUỘC -->
            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">2.2 Các Trạng Thái & Ràng Buộc (States & Constraints)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <x-ui.input
                        label="Trường bắt buộc (Required)"
                        name="required_field"
                        placeholder="Bắt buộc phải nhập..."
                        required
                    />

                    <x-ui.input
                        label="Trường có văn bản hướng dẫn (Hint)"
                        name="hint_field"
                        placeholder="Nhập mã định danh..."
                        hint="Mã gồm 6 chữ số in hoa theo quy chuẩn hồ sơ."
                    />

                    <x-ui.input
                        label="Trường báo lỗi xác thực (Error)"
                        name="invalid_field"
                        value="Dữ liệu không đúng"
                        error="Số điện thoại không đúng định dạng mạng viễn thông Việt Nam."
                    />

                    <x-ui.input
                        label="Ô nhập bị vô hiệu hóa (Disabled)"
                        name="disabled_field"
                        value="Dữ liệu cố định không thể sửa"
                        disabled
                    />

                    <x-ui.input
                        label="Ô nhập chỉ đọc (Readonly)"
                        name="readonly_field"
                        value="103 Phố Trung Liệt, Đống Đa, Hà Nội"
                        readonly
                    />

                    <x-ui.input
                        label="Kèm cả tiền tố và hậu tố (Prefix & Suffix)"
                        name="discount_amount"
                        type="number"
                        prefix="payments"
                        suffix="VNĐ"
                        value="150000"
                    />
                </div>
            </div>

            <!-- 2.3 DROPDOWN LỰA CHỌN (x-ui.select) -->
            <div class="pt-4 border-t border-slate-100">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">2.3 Dropdown Lựa Chọn Luxury Spa (x-ui.select)</h4>
                    <span class="text-[11px] font-medium text-[#0F766E] bg-[#F0FDFA] px-2.5 py-0.5 rounded-full border border-[#99F6E4]">Hỗ trợ Prefix Icon, Rich Options, Searchable & Clearable</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Dropdown 1: Rich Options với Icon, Subtitle, Badge -->
                    <x-ui.select
                        label="Phân loại dịch vụ (Rich Options)"
                        name="service_category"
                        placeholder="Chọn phân loại dịch vụ..."
                        icon="category"
                        :options="[
                            'facial' => [
                                'label' => 'Chăm sóc da Facial',
                                'icon' => 'spa',
                                'desc' => 'Dưỡng ẩm, phục hồi chuyên sâu',
                                'badge' => 'Phổ biến',
                            ],
                            'body' => [
                                'label' => 'Massage trị liệu Body',
                                'icon' => 'self_improvement',
                                'desc' => 'Đá nóng, giảm đau nhức cổ vai gáy',
                                'badge' => 'Hot',
                            ],
                            'package' => [
                                'label' => 'Gói liệu trình chuyên sâu',
                                'icon' => 'package_2',
                                'desc' => 'Combo 5-10 buổi tiết kiệm 20%',
                                'badge' => 'Ưu đãi',
                            ],
                        ]"
                        required
                    />

                    <!-- Dropdown 2: Tìm kiếm nhanh Searchable + Nút Clearable -->
                    <x-ui.select
                        label="Kỹ thuật viên thực hiện (Searchable + Clearable)"
                        name="technician_id"
                        placeholder="Tìm theo tên hoặc chuyên môn..."
                        icon="person"
                        :searchable="true"
                        :clearable="true"
                        :options="[
                            'ktv_1' => [
                                'label' => 'KTV Nguyễn Văn Thắng',
                                'icon' => 'badge',
                                'desc' => 'Trưởng nhóm trị liệu · 5 năm KN',
                                'badge' => 'Chuyên gia',
                            ],
                            'ktv_2' => [
                                'label' => 'KTV Trần Thị Mai',
                                'icon' => 'badge',
                                'desc' => 'Chuyên viên da liễu Facial',
                                'badge' => 'Sẵn sàng',
                            ],
                            'ktv_3' => [
                                'label' => 'KTV Lê Hoàng Yến',
                                'icon' => 'badge',
                                'desc' => 'KTV Massage đá nóng Body',
                                'badge' => 'Sẵn sàng',
                            ],
                            'ktv_4' => [
                                'label' => 'KTV Phạm Thu Hà',
                                'icon' => 'badge',
                                'desc' => 'KTV Gội đầu dưỡng sinh thảo dược',
                                'badge' => 'Ca chiều',
                            ],
                        ]"
                    />

                    <!-- Dropdown 3: Hạng hội viên với Selected value & Icon -->
                    <x-ui.select
                        label="Hạng hội viên khách hàng (Selected)"
                        name="membership_tier"
                        icon="workspace_premium"
                        :options="[
                            'standard' => [
                                'label' => 'Hội viên Tiêu chuẩn',
                                'icon' => 'star_outline',
                                'desc' => 'Tích lũy điểm thông thường',
                            ],
                            'silver' => [
                                'label' => 'Hội viên Bạc',
                                'icon' => 'star_half',
                                'desc' => 'Chiết khấu 5% mọi dịch vụ',
                                'badge' => '5%',
                            ],
                            'gold' => [
                                'label' => 'Hội viên Vàng',
                                'icon' => 'workspace_premium',
                                'desc' => 'Chiết khấu 10% + Ưu tiên xếp phòng',
                                'badge' => 'VIP 10%',
                            ],
                            'diamond' => [
                                'label' => 'Hội viên Kim cương',
                                'icon' => 'diamond',
                                'desc' => 'Chiết khấu 15% + Quà tặng sinh nhật',
                                'badge' => 'VVIP 15%',
                            ],
                        ]"
                        value="gold"
                    />

                    <!-- Dropdown 4: Slot <option> với data attributes -->
                    <x-ui.select
                        label="Chọn phòng thực hiện (Slot <option>)"
                        name="room_slot"
                        placeholder="Chọn phòng chăm sóc..."
                        icon="meeting_room"
                    >
                        <option value="101" data-icon="meeting_room" data-desc="Tầng 1 · 2 giường VIP · Sẵn sàng" data-badge="Trống">Phòng Chăm Sóc Da VIP 101</option>
                        <option value="102" data-icon="meeting_room" data-desc="Tầng 1 · 3 giường Body · Yên tĩnh" data-badge="Trống">Phòng Trị Liệu Body 102</option>
                        <option value="103" data-icon="meeting_room" data-desc="Tầng 2 · 4 bồn dưỡng sinh thảo mộc" data-badge="Có khách">Phòng Gội Đầu Dưỡng Sinh 103</option>
                    </x-ui.select>

                    <!-- Dropdown 5: Dropdown báo lỗi Validation -->
                    <x-ui.select
                        label="Dropdown báo lỗi (Validation Error)"
                        name="invalid_select"
                        placeholder="Chọn khung giờ hẹn..."
                        icon="schedule"
                        :options="[
                            'ca_1' => '09:00 - 10:30 (Khung giờ sáng)',
                            'ca_2' => '14:00 - 15:30 (Khung giờ chiều)',
                        ]"
                        error="Vui lòng chọn khung giờ hợp lệ còn giường trống."
                    />

                    <!-- Dropdown 6: Dropdown vô hiệu hóa (Disabled) -->
                    <x-ui.select
                        label="Dropdown vô hiệu hóa (Disabled State)"
                        name="disabled_select"
                        icon="storefront"
                        :options="[
                            'hn' => 'Cơ sở chính 103 Phố Trung Liệt, Đống Đa',
                        ]"
                        value="hn"
                        disabled
                    />
                </div>
            </div>

            <!-- 2.4 HỘP VĂN BẢN NHIỀU DÒNG (x-ui.textarea) -->
            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">2.4 Hộp Văn Bản Nhiều Dòng (x-ui.textarea)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <x-ui.textarea
                        label="Ghi chú liệu trình (Tiêu chuẩn)"
                        name="treatment_notes"
                        rows="3"
                        placeholder="Nhập ghi chú tình trạng da, tiền sử kích ứng của khách hàng..."
                    />

                    <x-ui.textarea
                        label="Yêu cầu đặc biệt (Có hint & Maxlength)"
                        name="special_requests"
                        rows="3"
                        placeholder="Ghi chú thêm cho kỹ thuật viên..."
                        maxlength="200"
                        hint="Tối đa 200 ký tự. Ghi rõ sở thích về nhiệt độ tinh dầu và lực massage."
                    />

                    <x-ui.textarea
                        label="Ghi chú báo lỗi (Error)"
                        name="invalid_textarea"
                        rows="3"
                        value="Nội dung đã nhập vượt quá giới hạn cho phép."
                        error="Ghi chú liệu trình vượt quá số ký tự quy định."
                    />

                    <x-ui.textarea
                        label="Ghi chú chỉ đọc (Readonly)"
                        name="readonly_textarea"
                        rows="3"
                        readonly
                        value="Quy định an toàn vệ sinh: Khử khuẩn toàn bộ dụng cụ lăn kim và thay khăn trải giường sau mỗi lượt khách."
                    />

                    <x-ui.textarea
                        label="Văn bản vô hiệu hóa (Disabled)"
                        name="disabled_textarea"
                        rows="3"
                        disabled
                        value="Hồ sơ bệnh án da liễu cũ đã được lưu trữ và khóa sổ."
                    />
                </div>
            </div>

            <!-- 2.5 HỘP CHỌN & NÚT CHỌN (x-ui.checkbox & x-ui.radio) -->
            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">2.5 Hộp Chọn & Nút Chọn (x-ui.checkbox & x-ui.radio)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Nhóm Checkbox -->
                    <div class="space-y-4 p-4 rounded-[10px] border border-slate-200 bg-slate-50/50">
                        <h5 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Hộp chọn kiểm tra (x-ui.checkbox)</h5>

                        <x-ui.checkbox
                            label="Gửi hóa đơn điện tử qua Zalo/SMS cho khách hàng"
                            name="send_invoice"
                        />

                        <x-ui.checkbox
                            label="Đăng ký tích lũy điểm hội viên"
                            description="Tự động tích lũy 5% giá trị hóa đơn thanh toán vào tài khoản thành viên Sunny Beauty."
                            name="register_points"
                            checked
                        />

                        <x-ui.checkbox
                            label="Khách hàng quen thuộc (Checked)"
                            name="returning_customer"
                            checked
                        />

                        <x-ui.checkbox
                            label="Áp dụng mã voucher sinh nhật (Disabled)"
                            description="Chương trình ưu đãi chỉ kích hoạt trong tuần lễ sinh nhật của khách."
                            name="birthday_voucher"
                            disabled
                        />

                        <x-ui.checkbox
                            label="Tôi xác nhận đã kiểm tra phiếu khám da trước trị liệu"
                            name="skin_checked"
                            error="Bắt buộc phải kiểm tra phiếu khám da trước khi điều phối giường."
                        />
                    </div>

                    <!-- Nhóm Radio -->
                    <div class="space-y-4 p-4 rounded-[10px] border border-slate-200 bg-slate-50/50">
                        <h5 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Nút chọn đơn (x-ui.radio)</h5>

                        <div class="space-y-2.5">
                            <span class="text-[12px] font-semibold text-[#475569] block">Phương thức thanh toán tại quầy:</span>
                            <x-ui.radio
                                label="Thanh toán tiền mặt tại quầy"
                                description="Thu ngân nhận tiền mặt trực tiếp và in biên lai giao cho khách."
                                name="payment_method"
                                value="cash"
                                checked
                            />

                            <x-ui.radio
                                label="Chuyển khoản ngân hàng (VietQR)"
                                description="Quét mã VietQR động để chuyển khoản trực tiếp vào tài khoản cửa hàng."
                                name="payment_method"
                                value="bank_transfer"
                            />
                        </div>

                        <div class="pt-3 border-t border-slate-200/80 space-y-2.5">
                            <span class="text-[12px] font-semibold text-[#475569] block">Các trạng thái khác:</span>
                            <x-ui.radio
                                label="Tùy chọn thanh toán qua POS thẻ (Vô hiệu hóa)"
                                description="Máy POS đang trong thời gian bảo trì định kỳ."
                                name="pos_payment"
                                value="pos"
                                disabled
                            />

                            <x-ui.radio
                                label="Cam kết thực hiện đúng quy trình khử khuẩn"
                                name="confirm_sterile"
                                value="confirmed"
                                error="Vui lòng tích chọn xác nhận quy trình khử khuẩn trước khi tiếp nhận."
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- KHU VỰC 3: HUY HIỆU TRẠNG THÁI & NHÃN PHÂN LOẠI (x-ui.badge) -->
    <section class="spa-card" style="margin-bottom: 24px;">
        <div class="spa-card-header">
            <div>
                <h2>3. Huy Hiệu Trạng Thái & Nhãn Phân Loại (x-ui.badge)</h2>
                <p>Hỗ trợ đầy đủ: Pill trạng thái, Tag danh mục, Badge số đếm chưa đọc, Chip bộ lọc có thể xóa (aria-label).</p>
            </div>
            <x-ui.badge variant="success">Hoàn thành</x-ui.badge>
        </div>
        <div class="spa-card-body">
            <div class="space-y-4">
                <!-- 3.1 Fully rounded Pill (Trạng thái ngắn có Biểu tượng tương ứng) -->
                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">1. Fully Rounded Pill (Trạng thái ngắn với Biểu tượng trực quan)</h4>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.badge variant="success" icon="check">Đã thanh toán (Success)</x-ui.badge>
                        <x-ui.badge variant="warning" icon="schedule">Chờ thanh toán (Warning)</x-ui.badge>
                        <x-ui.badge variant="danger" icon="cancel">Đã hủy / Thất bại (Danger)</x-ui.badge>
                        <x-ui.badge variant="danger" icon="build">Bảo trì (Danger)</x-ui.badge>
                        <x-ui.badge variant="info" icon="check">Đang hoạt động (Info)</x-ui.badge>
                        <x-ui.badge variant="neutral" icon="help">Chưa phân loại (Neutral)</x-ui.badge>
                        <x-ui.badge variant="success" :icon="false">Không có icon</x-ui.badge>
                    </div>
                </div>

                <!-- 3.2 Tag (Category Metadata - shadcn/ui style) -->
                <div class="pt-3 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">2. Tag (Metadata danh mục & Nhãn shadcn/ui non-interactive)</h4>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <x-ui.badge shape="tag" variant="neutral">Chăm sóc da Facial</x-ui.badge>
                        <x-ui.badge shape="tag" variant="info">Massage Trị Liệu</x-ui.badge>
                        <x-ui.badge shape="tag" variant="primary">Gói Liệu Trình VIP</x-ui.badge>
                        <x-ui.badge shape="tag" variant="warning">Hội viên Vàng</x-ui.badge>
                        <x-ui.badge shape="tag" variant="success">Đã kiểm toán</x-ui.badge>
                        <x-ui.badge shape="tag" variant="neutral" size="sm">Tag nhỏ (sm)</x-ui.badge>
                    </div>
                </div>

                <!-- 3.3 Badge (Compact Unread Count) -->
                <div class="pt-3 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">3. Badge (Số đếm Compact Unread Count)</h4>
                    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                        <div class="inline-flex items-center gap-1.5">
                            <span>Tin nhắn mới:</span>
                            <x-ui.badge shape="badge" variant="danger">3</x-ui.badge>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <span>Lịch hẹn chờ duyệt:</span>
                            <x-ui.badge shape="badge" variant="warning">8</x-ui.badge>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <span>Thông báo:</span>
                            <x-ui.badge shape="badge" variant="primary">12</x-ui.badge>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <span>Số đếm 99+:</span>
                            <x-ui.badge shape="badge" variant="danger">99+</x-ui.badge>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <span>Kích thước nhỏ (sm):</span>
                            <x-ui.badge shape="badge" variant="info" size="sm">5</x-ui.badge>
                        </div>
                    </div>
                </div>

                <!-- 3.4 Dismissible Chip (Active Filter với aria-label) -->
                <div class="pt-3 border-t border-slate-100">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">4. Dismissible Chip (Bộ lọc đang chọn có nút X & aria-label)</h4>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <x-ui.badge
                            variant="primary"
                            shape="tag"
                            dismissible
                            dismissLabel="Xóa bộ lọc danh mục Facial"
                            @dismiss="console.log('Đã xóa bộ lọc Facial')"
                        >
                            Danh mục: Chăm sóc da Facial
                        </x-ui.badge>

                        <x-ui.badge
                            variant="info"
                            shape="tag"
                            dismissible
                            dismissLabel="Xóa bộ lọc KTV Nguyễn Văn Thắng"
                            @dismiss="console.log('Đã xóa bộ lọc KTV')"
                        >
                            KTV: Nguyễn Văn Thắng
                        </x-ui.badge>

                        <x-ui.badge
                            variant="warning"
                            shape="pill"
                            dismissible
                            dismissLabel="Xóa bộ lọc trạng thái Chờ thanh toán"
                            @dismiss="console.log('Đã xóa bộ lọc trạng thái')"
                        >
                            Trạng thái: Chờ thanh toán
                        </x-ui.badge>

                        <x-ui.badge
                            variant="neutral"
                            shape="tag"
                            dismissible
                            dismissLabel="Xóa bộ lọc thời gian Hôm nay"
                        >
                            Thời gian: Hôm nay
                        </x-ui.badge>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- KHU VỰC 4: THÔNG BÁO & TOAST SNACKBAR (x-ui.alert) -->
    <section class="spa-card" style="margin-bottom: 24px;">
        <div class="spa-card-header">
            <div>
                <h2>4. Khung Thông Báo & Toast / Snackbar (x-ui.alert)</h2>
                <p>Hỗ trợ Alert inline và Toast (Snackbar) thông qua live region role="status", tự động ẩn sau 4s (tạm dừng khi hover hoặc focus phím).</p>
            </div>
            <x-ui.badge variant="success">Hoàn thành</x-ui.badge>
        </div>
        <div class="spa-card-body space-y-5">
            <div>
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">4.1 Thông báo tĩnh dạng Inline (Card Alerts)</h4>
                <div class="space-y-3">
                    <x-ui.alert variant="success" title="Cập nhật thành công" dismissible>
                        Đã lưu bảng tính lương tháng 09/2026 và ghi nhận nhật ký kiểm toán vào hệ thống.
                    </x-ui.alert>

                    <x-ui.alert variant="warning" title="Cảnh báo ca làm việc">
                        Có 2 kỹ thuật viên đang bị xếp trùng lịch trong khung giờ 14:00 – 15:30.
                    </x-ui.alert>

                    <x-ui.alert variant="danger" title="Lỗi hệ thống cần lưu ý" dismissible>
                        Không thể kết nối máy in hóa đơn tại quầy thu ngân. Vui lòng kiểm tra cáp kết nối.
                    </x-ui.alert>

                    <x-ui.alert variant="info" title="Thông tin vận hành">
                        Giờ cao điểm phục vụ bắt đầu từ 17:30 đến 20:00 hằng ngày.
                    </x-ui.alert>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">4.2 Trải nghiệm Toast / Snackbar (Góc dưới màn hình)</h4>
                <p class="text-xs text-slate-600 mb-3">
                    Bấm các nút dưới đây để kích hoạt Toast. Toast thành công/thông tin có <code>role="status"</code> tự tắt sau 4s; rê chuột vào hoặc tab phím vào nút X để <strong>tạm dừng bộ đếm</strong>. Toast lỗi không tự tắt.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button
                        variant="primary"
                        icon="check_circle"
                        onclick="window.dispatchEvent(new CustomEvent('notify', { detail: { variant: 'success', title: 'Thao tác thành công', message: 'Đã cập nhật dịch vụ thành công! (Tự tắt sau 4s, hover/focus để tạm dừng).' } }))"
                    >
                        Bắn Toast Thành Công
                    </x-ui.button>

                    <x-ui.button
                        variant="danger"
                        icon="error"
                        onclick="window.dispatchEvent(new CustomEvent('notify', { detail: { variant: 'danger', title: 'Có lỗi xảy ra', message: 'Không thể kết nối máy in hóa đơn. Toast lỗi giữ nguyên, không tự tắt.' } }))"
                    >
                        Bắn Toast Lỗi (Persistent)
                    </x-ui.button>

                    <x-ui.button
                        variant="secondary"
                        icon="info"
                        onclick="window.dispatchEvent(new CustomEvent('notify', { detail: { variant: 'info', title: 'Lịch hẹn mới', message: 'Khách hàng vừa đặt lịch hẹn lúc 16:30.' } }))"
                    >
                        Bắn Toast Thông Tin
                    </x-ui.button>
                </div>
            </div>
        </div>

        <!-- Khung cố định chứa Toast ở góc dưới bên phải màn hình (Non-blocking Corner) -->
        <div
            x-data="{
                toasts: [],
                add(variant, title, message) {
                    const id = Date.now() + Math.random();
                    this.toasts.push({ id, variant, title, message });
                },
                remove(id) {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }
            }"
            @notify.window="add($event.detail.variant, $event.detail.title, $event.detail.message)"
            class="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none p-4"
            style="min-height: 0;"
        >
            <template x-for="t in toasts" :key="t.id">
                <div class="pointer-events-auto">
                    <template x-if="t.variant === 'success'">
                        <x-ui.alert variant="success" toast x-bind:title="t.title" @toast-closed="remove(t.id)">
                            <span x-text="t.message"></span>
                        </x-ui.alert>
                    </template>
                    <template x-if="t.variant === 'danger'">
                        <x-ui.alert variant="danger" toast x-bind:title="t.title" @toast-closed="remove(t.id)">
                            <span x-text="t.message"></span>
                        </x-ui.alert>
                    </template>
                    <template x-if="t.variant === 'info'">
                        <x-ui.alert variant="info" toast x-bind:title="t.title" @toast-closed="remove(t.id)">
                            <span x-text="t.message"></span>
                        </x-ui.alert>
                    </template>
                    <template x-if="t.variant === 'warning'">
                        <x-ui.alert variant="warning" toast x-bind:title="t.title" @toast-closed="remove(t.id)">
                            <span x-text="t.message"></span>
                        </x-ui.alert>
                    </template>
                </div>
            </template>
        </div>
    </section>

    <!-- KHU VỰC 5: BẢNG DỮ LIỆU & TRẠNG THÁI TRỐNG (x-ui.table & x-ui.empty-state) -->
    <section class="spa-card" style="margin-bottom: 24px;">
        <div class="spa-card-header">
            <div>
                <h2>5. Bảng Dữ Liệu & Trạng Thái Trống (x-ui.table & x-ui.empty-state)</h2>
                <p>Khung cuộn ngang trên mobile, slot header, rows và slot empty state chuẩn.</p>
            </div>
            <x-ui.badge variant="success">Hoàn thành</x-ui.badge>
        </div>
        <div class="spa-card-body space-y-6">
            <div>
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Bảng có dữ liệu mẫu:</h4>
                <x-ui.table>
                    <x-slot:header>
                        <th>Mã liệu trình</th>
                        <th>Tên dịch vụ</th>
                        <th>Thời lượng</th>
                        <th>Giá niêm yết</th>
                        <th>Trạng thái</th>
                        <th class="text-right">Thao tác</th>
                    </x-slot:header>

                    <tr>
                        <td><strong>DV001</strong></td>
                        <td><strong>Chăm Sóc Da Trẻ Hóa Chuyên Sâu</strong><br><span class="spa-muted spa-small">Dịch vụ nổi bật</span></td>
                        <td>90 phút</td>
                        <td><strong>1.200.000 ₫</strong></td>
                        <td><x-ui.badge variant="success">Đang hoạt động</x-ui.badge></td>
                        <td class="text-right">
                            <x-ui.button variant="secondary" size="sm" icon="edit">Sửa</x-ui.button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>PK002</strong></td>
                        <td><strong>Gói Massage Cổ Vai Gáy (10 Buổi)</strong><br><span class="spa-muted spa-small">Thẻ liệu trình</span></td>
                        <td>60 phút</td>
                        <td><strong>4.500.000 ₫</strong></td>
                        <td><x-ui.badge variant="warning">Chờ duyệt</x-ui.badge></td>
                        <td class="text-right">
                            <x-ui.button variant="secondary" size="sm" icon="edit">Sửa</x-ui.button>
                        </td>
                    </tr>
                </x-ui.table>
            </div>

            <div>
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Bảng khi không có dữ liệu (Slot Empty State):</h4>
                <x-ui.table>
                    <x-slot:header>
                        <th>Mã KH</th>
                        <th>Họ và tên</th>
                        <th>Số điện thoại</th>
                        <th>Hạng thẻ</th>
                        <th>Thao tác</th>
                    </x-slot:header>

                    <x-slot:empty>
                        <x-ui.empty-state
                            title="Không tìm thấy khách hàng nào"
                            description="Chưa có hồ sơ khách hàng phù hợp với điều kiện tìm kiếm hiện tại."
                            icon="person_search"
                        >
                            <x-slot:action>
                                <x-ui.button variant="primary" size="sm" icon="add">Thêm khách hàng mới</x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    </x-slot:empty>
                </x-ui.table>
            </div>
        </div>
    </section>

    <!-- KHU VỰC 6: HỘP THOẠI MODAL (x-ui.modal) -->
    <section class="spa-card">
        <div class="spa-card-header">
            <div>
                <h2>6. Hộp Thoại Modal (x-ui.modal)</h2>
                <p>Sử dụng Alpine thuần, phím tắt Escape, lớp phủ backdrop mờ, role="dialog" và aria-modal.</p>
            </div>
            <x-ui.badge variant="success">Hoàn thành</x-ui.badge>
        </div>
        <div class="spa-card-body">
            <p class="text-xs text-slate-600 mb-3">Nhấn nút bên dưới để kiểm tra trải nghiệm mở/đóng modal:</p>
            <div class="flex items-center gap-3">
                <x-ui.button
                    variant="primary"
                    icon="open_in_browser"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'demo-modal' }))"
                >
                    Mở Hộp Thoại Modal Demo
                </x-ui.button>
            </div>

            <!-- Modal Component -->
            <x-ui.modal name="demo-modal" title="Xác nhận chốt bảng lương kỳ 09/2026" maxWidth="md">
                <p class="m-0 mb-3">
                    Sau khi chốt kỳ lương, toàn bộ số liệu công, hoa hồng và các khoản giảm trừ sẽ được khóa sổ để thực hiện thanh toán chuyển khoản hoặc tiền mặt.
                </p>
                <x-ui.alert variant="warning" title="Lưu ý kiểm toán">
                    Thao tác này sẽ được ghi vào nhật ký kiểm toán với tài khoản <strong>{{ auth()->user()?->name ?? 'Quản trị viên' }}</strong>.
                </x-ui.alert>

                <x-slot:actions>
                    <x-ui.button
                        variant="secondary"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'demo-modal' }))"
                    >
                        Hủy bỏ
                    </x-ui.button>
                    <x-ui.button
                        variant="primary"
                        icon="check"
                        onclick="alert('Đã xác nhận chốt bảng lương!'); window.dispatchEvent(new CustomEvent('close-modal', { detail: 'demo-modal' }))"
                    >
                        Đồng ý chốt
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.modal>
        </div>
    </section>

    <!-- KHU VỰC 7: KHUNG TRANG & NÚT BIỂU TƯỢNG (x-ui.page-heading, x-ui.icon-button) -->
    <section class="spa-card" style="margin-top: 24px;">
        <div class="spa-card-header">
            <div>
                <h2>7. Khung Trang & Nút Biểu Tượng</h2>
                <p>Kiểm tra vùng tiêu đề trang, action bar responsive và nút biểu tượng có nhãn trợ năng.</p>
            </div>
            <x-ui.badge variant="success">Hoàn thành</x-ui.badge>
        </div>
        <div class="spa-card-body">
            <x-ui.page-heading title="Khách hàng & CRM liệu trình" description="Ví dụ dùng chung tại các màn hình quản trị.">
                <x-slot:actions>
                    <x-ui.button variant="secondary" icon="download">Xuất danh sách</x-ui.button>
                    <x-ui.button icon="person_add">Thêm khách hàng</x-ui.button>
                    <x-ui.icon-button icon="close" label="Đóng ví dụ" />
                </x-slot:actions>
            </x-ui.page-heading>
        </div>
    </section>
@endcomponent
