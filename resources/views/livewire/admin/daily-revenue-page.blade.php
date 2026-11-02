<div class="spa-daily-revenue-page space-y-5">
    <x-ui.page-heading title="Nhập cuối ngày" description="Ghi nhận khách, tiền thực thu và hoa hồng theo dịch vụ trong cùng một màn hình." context="Doanh thu & hoa hồng" icon="edit_note">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('services') }}" icon="percent">Cài hoa hồng dịch vụ</x-ui.button>
            @if (! $showForm || $period === 'month')
                <x-ui.button wire:click="newEntry" icon="add">Nhập thêm khách</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-heading>

    @if ($savedMessage !== '')
        <x-ui.alert variant="success">{{ $savedMessage }}</x-ui.alert>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-2" role="group" aria-label="Chế độ báo cáo">
            <x-ui.button :variant="$period === 'day' ? 'primary' : 'secondary'" wire:click="switchPeriod('day')" :aria-pressed="$period === 'day' ? 'true' : 'false'">Theo ngày</x-ui.button>
            <x-ui.button :variant="$period === 'month' ? 'primary' : 'secondary'" wire:click="switchPeriod('month')" :aria-pressed="$period === 'month' ? 'true' : 'false'">Tổng kết tháng</x-ui.button>
        </div>
        @if ($period === 'month')
            <x-ui.input label="Tháng báo cáo" type="month" wire:model.live="month" :value="$month" required />
        @else
        <x-ui.input label="Ngày ghi nhận" type="date" wire:model.live="date" :value="$date" required />
        @endif
        <p class="spa-muted max-w-xl text-sm">Một khách làm nhiều dịch vụ vẫn được tính một lần trong kỳ báo cáo. Chỉ nhập những khoản chưa có phiếu thu trong hệ thống.</p>
    </div>

    @error('date') <x-ui.alert variant="danger">{{ $message }}</x-ui.alert> @enderror

    <section aria-label="Tổng hợp đã ghi nhận" class="spa-summary-strip spa-summary-strip--3 spa-grid spa-grid-3">
        <div class="spa-stat"><div class="spa-stat-label">Khách trong {{ $periodLabel }}</div><div class="spa-stat-value">{{ $summary['customers'] }}</div><div class="spa-stat-note">Khách có phiếu thu đã thanh toán</div></div>
        <div class="spa-stat"><div class="spa-stat-label">Doanh thu {{ $periodLabel }}</div><div class="spa-stat-value">{{ number_format($summary['revenue'], 0, ',', '.') }} ₫</div><div class="spa-stat-note">Tiền thực thu trong kỳ được chọn</div></div>
        <div class="spa-stat"><div class="spa-stat-label">Hoa hồng {{ $periodLabel }}</div><div class="spa-stat-value">{{ number_format($summary['commission'], 0, ',', '.') }} ₫</div><div class="spa-stat-note">Gồm hoa hồng chờ chốt và đã chốt</div></div>
    </section>

    @if ($showForm && $period === 'day')
        <div @modal-closed="$wire.set('showForm', false)">
        <x-ui.modal name="daily-revenue-entry-modal" title="Khách và dịch vụ cần nhập" maxWidth="2xl" :show="true">
            <form wire:submit="save" novalidate class="space-y-5">
                <p class="spa-muted">Số tiền nhập là tiền thực thu sau giảm giá. Mỗi dịch vụ sẽ tạo một phiếu thu để đối soát và hoàn tiền độc lập.</p>
                @if ($errors->any())
                    <x-ui.alert variant="danger" title="Chưa lưu được số liệu">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif
                <fieldset wire:loading.attr="disabled" wire:target="save" class="min-w-0 space-y-5">
                    @foreach ($customers as $customerIndex => $row)
                        <div wire:key="customer-{{ $row['key'] }}" class="space-y-4 border-b border-[var(--spa-border)] pb-5">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold">Khách {{ $customerIndex + 1 }}</h3>
                                @if (count($customers) > 1)
                                    <x-ui.button variant="ghost" wire:click="removeCustomer({{ $customerIndex }})" aria-label="Bỏ khách {{ $customerIndex + 1 }} khỏi danh sách nhập">Bỏ khách</x-ui.button>
                                @endif
                            </div>
                            <div class="spa-grid spa-grid-3">
                                <x-ui.select label="Phân loại khách" wire:model.live="customers.{{ $customerIndex }}.customer_type" :selected="$row['customer_type'] ?? 'retail'" required>
                                    <option value="retail">Khách lẻ trong ngày</option>
                                    <option value="package">Khách đã mua gói dịch vụ</option>
                                </x-ui.select>
                                <x-ui.select label="Khách hàng" wire:model.live="customers.{{ $customerIndex }}.customer_id" :selected="$row['customer_id']" placeholder="Chọn khách hiện có hoặc thêm mới" searchable required>
                                    <option value="new">+ Thêm khách mới</option>
                                    @foreach ($customerOptions as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>
                                    @endforeach
                                </x-ui.select>
                                <x-ui.select label="Phương thức thu tiền" wire:model.live="customers.{{ $customerIndex }}.method" :selected="$row['method']" required>
                                    <option value="cash">Tiền mặt</option><option value="vietqr">VietQR</option><option value="pos">POS</option>
                                </x-ui.select>
                                @if ($row['customer_id'] === 'new')
                                    <x-ui.input label="Tên khách mới" wire:model.live.debounce.300ms="customers.{{ $customerIndex }}.name" maxlength="120" required />
                                    <x-ui.input label="Số điện thoại khách mới" type="tel" wire:model.live.debounce.300ms="customers.{{ $customerIndex }}.phone" maxlength="32" required />
                                @endif
                                @if ($row['method'] !== 'cash')
                                    <x-ui.input label="Mã giao dịch" wire:model="customers.{{ $customerIndex }}.reference" maxlength="120" required />
                                @endif
                            </div>
                            @if (($row['customer_type'] ?? 'retail') === 'package')
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-[12px] font-medium">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">check_circle</span>
                                    <span>Khách làm theo gói đã mua: Tiền thu mặc định <strong>0 ₫</strong> (hoặc phụ thu nếu có). Kỹ thuật viên vẫn được nhận đủ hoa hồng theo ca làm.</span>
                                </div>
                            @endif
                            @foreach ($row['services'] as $serviceIndex => $service)
                                <div wire:key="service-{{ $service['key'] }}" class="space-y-3 rounded-lg bg-[var(--spa-surface-muted)] p-3">
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                                        <x-ui.select label="Dịch vụ / Gói {{ $serviceIndex + 1 }}" wire:model.live="customers.{{ $customerIndex }}.services.{{ $serviceIndex }}.offering_id" :selected="$service['offering_id']" placeholder="Chọn dịch vụ hoặc gói" searchable required>
                                            @foreach ($offerings as $offering)
                                                <option value="{{ $offering->id }}">[{{ $offering->type === 'package' ? 'Gói liệu trình' : 'Dịch vụ lẻ' }}] {{ $offering->name }} · {{ number_format($offering->price, 0, ',', '.') }} ₫</option>
                                            @endforeach
                                        </x-ui.select>
                                        <x-ui.select label="Nhân viên thực hiện" wire:model.live="customers.{{ $customerIndex }}.services.{{ $serviceIndex }}.employee_id" :selected="$service['employee_id']" placeholder="Chọn nhân viên" searchable required>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->job_title }}</option>
                                            @endforeach
                                        </x-ui.select>
                                        <x-ui.input label="Tiền thực thu (₫)" type="number" min="0" max="1000000000" step="1" wire:model.live.debounce.300ms="customers.{{ $customerIndex }}.services.{{ $serviceIndex }}.amount" inputmode="numeric" required />
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="text-sm tabular-nums" aria-live="polite">
                                            @forelse ($quotes[$customerIndex][$serviceIndex] ?? [] as $quote)
                                                <p>{{ $quote['employee'] }} · {{ $quote['rate'] }} · <strong class="text-amber-700">{{ number_format($quote['amount'], 0, ',', '.') }} ₫ hoa hồng</strong>@if (!empty($quote['is_package'])) <span class="text-xs text-slate-500 font-normal">(Ca làm gói)</span>@endif</p>
                                            @empty
                                                <p class="spa-muted">{{ $service['offering_id'] && $service['employee_id'] && $service['amount'] !== '' ? 'Chưa có quy tắc hoa hồng áp dụng trong ngày này: 0 ₫.' : 'Chọn dịch vụ, nhân viên để xem trước hoa hồng.' }}</p>
                                            @endforelse
                                        </div>
                                        @if (count($row['services']) > 1)
                                            <x-ui.button variant="ghost" wire:click="removeService({{ $customerIndex }}, {{ $serviceIndex }})" aria-label="Bỏ dịch vụ {{ $serviceIndex + 1 }} của khách {{ $customerIndex + 1 }}">Bỏ dịch vụ</x-ui.button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            <x-ui.button variant="secondary" icon="add" wire:click="addService({{ $customerIndex }})" :disabled="count($row['services']) >= 10">Thêm dịch vụ cho khách này</x-ui.button>
                        </div>
                    @endforeach
                    <x-ui.button variant="secondary" icon="person_add" wire:click="addCustomer" :disabled="count($customers) >= 50">Thêm khách</x-ui.button>
                </fieldset>
                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-[var(--spa-border)] pt-4">
                    <div aria-live="polite" aria-atomic="true" class="space-y-1 text-sm tabular-nums">
                        <p class="font-semibold">Dự kiến lần nhập này: {{ $draft['customers'] }} khách · {{ number_format($draft['revenue'], 0, ',', '.') }} ₫ doanh thu</p>
                        <p>Hoa hồng dự kiến: <strong>{{ number_format($draft['commission'], 0, ',', '.') }} ₫</strong></p>
                        <p class="spa-muted">Số liệu dự kiến chỉ được ghi nhận sau khi lưu thành công.</p>
                    </div>
                    <x-ui.button type="submit" icon="save" loading="save">Lưu doanh thu & hoa hồng</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
        </div>
    @endif

    @if ($period === 'month')
        <section class="spa-card">
            <div class="spa-card-header"><div><h2>Doanh thu từng ngày trong {{ $periodLabel }}</h2><p>Tổng tiền thực thu theo ngày thanh toán. Chọn một ngày để xem chi tiết.</p></div></div>
            <x-ui.table mobile-cards>
                <x-slot:header><th>Ngày</th><th>Số khách</th><th>Doanh thu</th><th>Hoa hồng</th><th>Chi tiết</th></x-slot:header>
                @forelse ($days as $day => $row)
                    <tr wire:key="report-day-{{ $day }}">
                        <td data-label="Ngày">{{ \Carbon\CarbonImmutable::parse($day)->format('d/m/Y') }}</td>
                        <td data-label="Số khách">{{ $row['customers'] }}</td>
                        <td data-label="Doanh thu" class="tabular-nums">{{ number_format($row['revenue'], 0, ',', '.') }} ₫</td>
                        <td data-label="Hoa hồng" class="tabular-nums">{{ number_format($row['commission'], 0, ',', '.') }} ₫</td>
                        <td data-label="Chi tiết"><x-ui.button variant="ghost" wire:click="viewDay('{{ $day }}')" :disabled="$day > today()->toDateString()" aria-label="Xem ngày {{ $day }}">Xem ngày</x-ui.button></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state title="Chưa có số liệu trong tháng" description="Chọn tháng khác hoặc ghi nhận dịch vụ để xem báo cáo." /></td></tr>
                @endforelse
            </x-ui.table>
        </section>
    @endif

    <details class="spa-card spa-revenue-staff" wire:key="revenue-staff-report">
        <summary>Doanh thu và hoa hồng nhân viên — {{ $periodLabel }}</summary>
        <p class="spa-muted px-4 pb-3">Doanh thu theo người thực hiện; hoa hồng theo người hưởng đã cài đặt. Số khách của các nhân viên có thể trùng nhau.</p>
        <x-ui.table mobile-cards>
            <x-slot:header><th>Nhân viên</th><th>Số khách</th><th>Doanh thu thực thu</th><th>Hoa hồng</th></x-slot:header>
            @forelse ($staff as $employeeId => $row)
                <tr wire:key="staff-{{ $employeeId }}">
                    <td data-label="Nhân viên"><strong>{{ $row['name'] }}</strong></td>
                    <td data-label="Số khách">{{ $row['customers'] }}</td>
                    <td data-label="Doanh thu thực thu" class="tabular-nums">{{ number_format($row['revenue'], 0, ',', '.') }} ₫</td>
                    <td data-label="Hoa hồng" class="tabular-nums"><strong>{{ number_format($row['commission'], 0, ',', '.') }} ₫</strong></td>
                </tr>
            @empty
                <tr><td colspan="4"><x-ui.empty-state title="Chưa có số liệu nhân viên" description="Nhập và lưu khách cùng dịch vụ để xem doanh thu và hoa hồng." /></td></tr>
            @endforelse
        </x-ui.table>
    </details>

    <section class="spa-card">
        <div class="spa-card-header"><div><h2>Chi tiết khách và dịch vụ — {{ $periodLabel }}</h2><p>Gồm phiếu nhập cuối ngày và phiếu thu hiện có, kể cả gói liệu trình. Loại phiếu đã hoàn tiền và hoa hồng đã đảo.</p></div><x-ui.button variant="secondary" href="{{ route('invoices') }}">Mở sổ phiếu thu</x-ui.button></div>
        <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
            <x-ui.input label="Tìm khách theo tên hoặc SĐT" wire:model.live.debounce.300ms="search" maxlength="120" placeholder="Nhập tên hoặc số điện thoại" />
            <x-ui.select label="Nhân viên thực hiện hoặc hưởng hoa hồng" placeholder="Tất cả nhân viên" wire:model.live="employeeFilter" :selected="$employeeFilter" searchable>
                <option value="">Tất cả nhân viên</option>
                @foreach ($reportEmployees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}{{ $employee->trashed() ? ' · Đã lưu trữ' : '' }}</option>
                @endforeach
            </x-ui.select>
            <p class="spa-muted md:col-span-2">Bộ lọc áp dụng cho bảng chi tiết; tổng doanh thu và hoa hồng phía trên vẫn là toàn bộ {{ $periodLabel }}. Phiếu nhiều dịch vụ phân bổ tiền thực thu theo giá trị từng dòng. Với phiếu cũ có dịch vụ lặp, hoa hồng của dịch vụ đó hiển thị ở dòng đầu tiên.</p>
        </div>
        <x-ui.table mobile-cards class="spa-revenue-details">
            <x-slot:header><th>Ngày thu</th><th>Khách hàng</th><th>SĐT</th><th>Dịch vụ / gói</th><th>Tiền thực thu</th><th>Nhân viên thực hiện</th><th>Mức hưởng / người nhận</th><th>Hoa hồng</th><th>Phiếu thu</th></x-slot:header>
            @forelse ($details as $detail)
                <tr wire:key="revenue-item-{{ $detail['item']->id }}">
                    <td data-label="Ngày thu">{{ $detail['invoice']->paid_at->format('d/m/Y') }}</td>
                    <td data-label="Khách hàng"><strong>{{ $detail['invoice']->customer?->name ?? 'Khách đã lưu trữ' }}</strong></td>
                    <td data-label="SĐT">{{ $detail['invoice']->customer?->phone ?? '—' }}</td>
                    <td data-label="Dịch vụ / gói">{{ $detail['item']->description }}</td>
                    <td data-label="Tiền thực thu" class="tabular-nums"><strong>{{ number_format($detail['amount'], 0, ',', '.') }} ₫</strong></td>
                    <td data-label="Nhân viên thực hiện">{{ $detail['item']->employee?->name ?? 'Chưa gán' }}</td>
                    <td data-label="Mức hưởng / người nhận">
                        @forelse ($detail['commissions'] as $commission)
                            <p>{{ $commission->employee?->name ?? 'Nhân viên đã lưu trữ' }} · {{ $commission->calculation_type === 'fixed' ? number_format((float) $commission->rate, 0, ',', '.') . ' ₫/lượt' : rtrim(rtrim(number_format((float) $commission->rate, 2, ',', '.'), '0'), ',') . '%' }}</p>
                        @empty — @endforelse
                    </td>
                    <td data-label="Hoa hồng" class="tabular-nums"><strong>{{ number_format($detail['commissions']->sum('amount'), 0, ',', '.') }} ₫</strong></td>
                    <td data-label="Phiếu thu"><x-ui.button variant="ghost" href="{{ route('invoices.print', $detail['invoice']) }}" target="_blank" rel="noopener">{{ $detail['invoice']->code }}</x-ui.button></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-ui.empty-state title="Chưa có dịch vụ phù hợp" description="Chọn ngày hoặc tháng khác, điều chỉnh bộ lọc hoặc ghi nhận dịch vụ mới." /></td></tr>
            @endforelse
        </x-ui.table>
        <p class="spa-muted p-4">{{ $entries->total() }} phiếu thu phù hợp · Mỗi dòng hiển thị một dịch vụ.</p>
        {{ $entries->links() }}
    </section>
</div>
