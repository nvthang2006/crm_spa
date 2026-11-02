<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BladeUiComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_can_open_ui_preview_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.ui-preview'));

        $response->assertOk();
        $response->assertSee('Bộ Blade UI Component Nền Tảng');
        $response->assertSee('x-ui.button');
        $response->assertSee('x-ui.input');
        $response->assertSee('x-ui.select');
        $response->assertSee('x-ui.badge');
        $response->assertSee('x-ui.alert');
        $response->assertSee('x-ui.table');
        $response->assertSee('x-ui.modal');
        $response->assertSee('x-ui.empty-state');
        $response->assertSee('x-ui.page-heading');
        $response->assertSee('x-ui.icon-button');
    }

    public function test_authenticated_admin_can_open_print_views_after_component_migration(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($user)
            ->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('spa-table-wrap');

        $this->actingAs($user)
            ->get(route('invoices.shift-report', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('spa-table-wrap');
    }

    public function test_button_component_renders_button_and_link(): void
    {
        $htmlButton = Blade::render('<x-ui.button variant="primary" icon="add">Thêm dịch vụ</x-ui.button>');
        $this->assertStringContainsString('<button', $htmlButton);
        $this->assertStringContainsString('spa-button-primary', $htmlButton);
        $this->assertStringContainsString('Thêm dịch vụ', $htmlButton);
        $this->assertStringContainsString('add', $htmlButton);

        $htmlLink = Blade::render('<x-ui.button variant="secondary" href="/dashboard">Về trang chủ</x-ui.button>');
        $this->assertStringContainsString('<a', $htmlLink);
        $this->assertStringContainsString('href="/dashboard"', $htmlLink);
        $this->assertStringContainsString('spa-button-secondary', $htmlLink);
        $this->assertStringContainsString('Về trang chủ', $htmlLink);
    }

    public function test_input_component_renders_label_and_linked_id(): void
    {
        $html = Blade::render('<x-ui.input label="Số điện thoại" name="phone" required hint="Ghi chú số chính" />');

        $this->assertStringContainsString('<label for="input-phone"', $html);
        $this->assertStringContainsString('id="input-phone"', $html);
        $this->assertStringContainsString('name="phone"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('aria-describedby="input-phone-hint"', $html);
        $this->assertStringContainsString('Ghi chú số chính', $html);
    }

    public function test_input_component_renders_error_state(): void
    {
        $html = Blade::render('<x-ui.input label="Email" name="email" error="Email không hợp lệ" />');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Email không hợp lệ', $html);
    }

    public function test_select_component_renders_options_and_slot(): void
    {
        $htmlWithOptions = Blade::render('<x-ui.select label="Loại" name="type" placeholder="Chọn loại" :options="[\'a\' => \'Loại A\']" />');
        $this->assertStringContainsString('<select', $htmlWithOptions);
        $this->assertStringContainsString('Chọn loại', $htmlWithOptions);
        $this->assertStringContainsString('value="a"', $htmlWithOptions);
        $this->assertStringContainsString('Loại A', $htmlWithOptions);

        $htmlWithSlot = Blade::render('<x-ui.select label="Phòng" name="room"><option value="1">Phòng 101</option></x-ui.select>');
        $this->assertStringContainsString('Phòng 101', $htmlWithSlot);
    }

    public function test_select_component_exposes_aria_label_on_custom_trigger(): void
    {
        $html = Blade::render('<x-ui.select name="status" aria-label="Lọc theo trạng thái"><option value="active">Đang hoạt động</option></x-ui.select>');

        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-label="Lọc theo trạng thái"', $html);
    }

    public function test_select_component_supports_prefix_icon_searchable_clearable_and_rich_options(): void
    {
        $htmlRich = Blade::render('<x-ui.select label="Hạng" name="tier" icon="workspace_premium" :searchable="true" :clearable="true" :options="[\'gold\' => [\'label\' => \'Hội viên Vàng\', \'icon\' => \'grade\', \'desc\' => \'Ưu đãi 10%\', \'badge\' => \'VIP\']]" value="gold" />');

        $this->assertStringContainsString('workspace_premium', $htmlRich);
        $this->assertStringContainsString('Tìm kiếm lựa chọn...', $htmlRich);
        $this->assertStringContainsString('title="Xóa lựa chọn"', $htmlRich);
        $this->assertStringContainsString('Hội viên Vàng', $htmlRich);
        $this->assertStringContainsString('Ưu đãi 10%', $htmlRich);
        $this->assertStringContainsString('VIP', $htmlRich);
    }

    public function test_badge_component_renders_variants_and_dots(): void
    {
        $htmlSuccess = Blade::render('<x-ui.badge variant="success">Đã thanh toán</x-ui.badge>');
        $this->assertStringContainsString('spa-badge-success', $htmlSuccess);
        $this->assertStringContainsString('Đã thanh toán', $htmlSuccess);
        $this->assertStringContainsString('check', $htmlSuccess);

        $htmlWarning = Blade::render('<x-ui.badge variant="warning" icon="schedule">Chờ thanh toán</x-ui.badge>');
        $this->assertStringContainsString('schedule', $htmlWarning);

        $htmlCustomIcon = Blade::render('<x-ui.badge variant="danger" icon="build">Bảo trì</x-ui.badge>');
        $this->assertStringContainsString('build', $htmlCustomIcon);

        $htmlDot = Blade::render('<x-ui.badge variant="warning" :dot="true">Cảnh báo</x-ui.badge>');
        $this->assertStringNotContainsString('before:!hidden', $htmlDot);

        $htmlTag = Blade::render('<x-ui.badge shape="tag" variant="neutral">Chăm sóc Facial</x-ui.badge>');
        $this->assertStringContainsString('rounded-[6px]', $htmlTag);
        $this->assertStringContainsString('Chăm sóc Facial', $htmlTag);

        $htmlCount = Blade::render('<x-ui.badge shape="badge" variant="danger">5</x-ui.badge>');
        $this->assertStringContainsString('min-w-[20px]', $htmlCount);
        $this->assertStringContainsString('5', $htmlCount);

        $htmlDismissible = Blade::render('<x-ui.badge variant="primary" dismissible dismissLabel="Xóa bộ lọc">Bộ lọc đang chọn</x-ui.badge>');
        $this->assertStringContainsString('<button', $htmlDismissible);
        $this->assertStringContainsString('aria-label="Xóa bộ lọc"', $htmlDismissible);
        $this->assertStringContainsString('close', $htmlDismissible);
    }

    public function test_alert_component_renders_semantics(): void
    {
        $htmlDanger = Blade::render('<x-ui.alert variant="danger" title="Lỗi">Có lỗi xảy ra</x-ui.alert>');
        $this->assertStringContainsString('role="alert"', $htmlDanger);
        $this->assertStringContainsString('Lỗi', $htmlDanger);
        $this->assertStringContainsString('Có lỗi xảy ra', $htmlDanger);

        $htmlSuccess = Blade::render('<x-ui.alert variant="success">Thành công</x-ui.alert>');
        $this->assertStringContainsString('role="status"', $htmlSuccess);

        $htmlToastSuccess = Blade::render('<x-ui.alert variant="success" toast title="Thành công">Đã lưu dữ liệu</x-ui.alert>');
        $this->assertStringContainsString('role="status"', $htmlToastSuccess);
        $this->assertStringContainsString('aria-live="polite"', $htmlToastSuccess);
        $this->assertStringContainsString('@mouseenter="pause()"', $htmlToastSuccess);
        $this->assertStringContainsString('@focusin="pause()"', $htmlToastSuccess);

        $htmlToastDanger = Blade::render('<x-ui.alert variant="danger" toast title="Lỗi">Lỗi hệ thống</x-ui.alert>');
        $this->assertStringContainsString('role="alert"', $htmlToastDanger);
        $this->assertStringContainsString('aria-live="assertive"', $htmlToastDanger);
    }

    public function test_table_and_empty_state_components(): void
    {
        $html = Blade::render('
            <x-ui.table>
                <x-slot:header><th>Cột 1</th></x-slot:header>
                <x-slot:empty><x-ui.empty-state title="Danh sách trống" /></x-slot:empty>
            </x-ui.table>
        ');

        $this->assertStringContainsString('spa-table-wrap', $html);
        $this->assertStringContainsString('Cột 1', $html);
        $this->assertStringContainsString('Danh sách trống', $html);
    }

    public function test_modal_component_renders_accessible_dialog(): void
    {
        $html = Blade::render('<x-ui.modal name="confirm-delete" title="Xác nhận xóa">Bạn có chắc chắn muốn xóa?</x-ui.modal>');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('Xác nhận xóa', $html);
        $this->assertStringContainsString('Bạn có chắc chắn muốn xóa?', $html);
    }

    public function test_page_heading_and_icon_button_components_render_accessibly(): void
    {
        $html = Blade::render(' 
            <x-ui.page-heading title="Khách hàng" description="Quản lý hồ sơ khách hàng.">
                <x-slot:actions>
                    <x-ui.icon-button icon="close" label="Đóng hồ sơ" />
                </x-slot:actions>
            </x-ui.page-heading>
        ');

        $this->assertStringContainsString('spa-page-heading', $html);
        $this->assertStringContainsString('id="page-heading-khach-hang"', $html);
        $this->assertStringContainsString('Quản lý hồ sơ khách hàng.', $html);
        $this->assertStringContainsString('aria-label="Đóng hồ sơ"', $html);
        $this->assertStringContainsString('>close</span>', $html);
    }

    public function test_action_card_and_tab_components_render_focusable_controls(): void
    {
        $html = Blade::render('
            <x-ui.action-card :active="true" wire:click="filter">Tất cả</x-ui.action-card>
            <x-ui.tab :active="true" wire:click="switchTab">Dịch vụ</x-ui.tab>
        ');

        $this->assertSame(2, substr_count($html, 'focus-visible:ring-2'));
        $this->assertStringContainsString('aria-pressed="true"', $html);
        $this->assertStringContainsString('spa-stat-clickable', $html);
        $this->assertStringContainsString('spa-tab-item', $html);
    }
}
