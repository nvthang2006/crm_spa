<?php

namespace App\Livewire\Admin;

use App\Models\Appointment;
use App\Models\ResourceArea;
use App\Models\ServiceResource;
use App\Services\AppointmentService;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RoomsPage extends Component
{
    public string $statusFilter = 'all';

    public string $areaFilter = 'all';

    public string $search = '';

    public bool $showMaintenance = false;

    public ?int $maintenanceId = null;

    public string $maintenanceResourceName = '';

    public string $maintenanceNote = '';

    public string $maintenanceUntil = '';

    public function filterByStatus(string $status): void
    {
        $this->statusFilter = ($this->statusFilter === $status && $status !== 'all') ? 'all' : $status;
    }

    public function resetFilters(): void
    {
        $this->statusFilter = 'all';
        $this->areaFilter = 'all';
        $this->search = '';
    }

    public function setReady(int $id): void
    {
        $resource = ServiceResource::query()->findOrFail($id);
        app(AppointmentService::class)->markResourceReady($resource);
        session()->flash('success', "Đã xác nhận phòng/giường {$resource->name} dọn xong và sẵn sàng đón khách.");
    }

    public function openMaintenance(int $id): void
    {
        $resource = ServiceResource::query()->findOrFail($id);
        if ($resource->status === 'busy') {
            session()->flash('error', 'Không thể bảo trì khi phòng/giường đang có ca phục vụ.');

            return;
        }
        $this->maintenanceId = $id;
        $this->maintenanceResourceName = $resource->name.' ('.$resource->code.')';
        $this->maintenanceNote = (string) $resource->maintenance_note;
        $this->maintenanceUntil = $resource->maintenance_until?->format('Y-m-d\\TH:i') ?? '';
        $this->showMaintenance = true;
    }

    public function saveMaintenance(): void
    {
        $validated = $this->validate([
            'maintenanceNote' => ['required', 'string', 'max:500'],
            'maintenanceUntil' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ], [
            'maintenanceNote.required' => 'Vui lòng nhập nội dung hoặc lý do bảo trì.',
            'maintenanceNote.max' => 'Nội dung bảo trì không được vượt quá 500 ký tự.',
            'maintenanceUntil.date_format' => 'Thời gian dự kiến không đúng định dạng ngày giờ.',
        ]);

        $resource = ServiceResource::query()->findOrFail($this->maintenanceId);
        if ($resource->status === 'busy') {
            throw ValidationException::withMessages(['maintenanceNote' => 'Không thể bảo trì khi phòng/giường đang có ca phục vụ.']);
        }

        $before = $resource->getOriginal();
        $maintenanceUntil = ! empty($validated['maintenanceUntil'])
            ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['maintenanceUntil'])->format('Y-m-d H:i:s')
            : null;

        $resource->update([
            'status' => 'maintenance',
            'maintenance_note' => $validated['maintenanceNote'],
            'maintenance_until' => $maintenanceUntil,
        ]);

        app(AuditService::class)->record('resource.maintenance_started', $resource, $before, $resource->fresh()->toArray());
        $this->showMaintenance = false;
        session()->flash('success', "Đã chuyển {$resource->name} sang trạng thái bảo trì.");
    }

    public function render()
    {
        // Danh sách tất cả khu vực để đưa vào dropdown filter
        $allAreas = ResourceArea::query()->where('status', 'active')->orderBy('id')->get();

        // Eager load active appointments để hiển thị thông tin ca phục vụ trên thẻ giường
        $activeAppointments = Appointment::query()
            ->whereIn('status', ['in_service', 'arrived'])
            ->with(['customer', 'offering', 'primaryEmployee', 'supportEmployee'])
            ->latest('started_at')
            ->get()
            ->groupBy('service_resource_id');

        // Danh sách khu vực kèm tài nguyên được lọc
        $areas = ResourceArea::query()
            ->with(['resources' => function ($query) {
                $query->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
                    ->when($this->search !== '', fn ($q) => $q->where(function ($sub) {
                        $sub->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('code', 'like', '%'.$this->search.'%');
                    }))
                    ->orderBy('name');
            }])
            ->when($this->areaFilter !== 'all', fn ($q) => $q->where('id', $this->areaFilter))
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        // Gắn thông tin ca phục vụ vào từng resource
        foreach ($areas as $area) {
            foreach ($area->resources as $resource) {
                $activeAppt = $activeAppointments->get($resource->id)?->first();
                if (! $activeAppt && $resource->status === 'busy') {
                    // Fallback lấy lịch gần nhất nếu dữ liệu mẫu chưa có started_at
                    $activeAppt = $resource->appointments()
                        ->latest('id')
                        ->with(['customer', 'offering', 'primaryEmployee', 'supportEmployee'])
                        ->first();
                }
                $resource->active_appointment = $activeAppt;
            }
        }

        // Tính toán thống kê toàn cơ sở
        $totalCount = ServiceResource::count();
        $busyCount = ServiceResource::where('status', 'busy')->count();
        $readyCount = ServiceResource::where('status', 'ready')->count();
        $cleaningCount = ServiceResource::where('status', 'cleaning')->count();
        $maintenanceCount = ServiceResource::where('status', 'maintenance')->count();
        $occupancyRate = $totalCount > 0 ? round(($busyCount / $totalCount) * 100) : 0;

        $stats = [
            'total' => $totalCount,
            'busy' => $busyCount,
            'ready' => $readyCount,
            'cleaning' => $cleaningCount,
            'maintenance' => $maintenanceCount,
            'occupancy_rate' => $occupancyRate,
        ];

        return view('livewire.admin.rooms-page', compact('areas', 'allAreas', 'stats'))
            ->layout('layouts.admin', ['title' => 'Sơ đồ Phòng & Giường']);
    }
}
