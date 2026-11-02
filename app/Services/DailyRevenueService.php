<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Invoice;
use App\Models\Offering;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DailyRevenueService
{
    /**
     * @param  array<int, array{key: string, customer_id: string, name: string, phone: string, method: string, reference: string, services: array<int, array{key: string, offering_id: string, employee_id: string, amount: string}>}>  $customers
     * @return Collection<int, Invoice>
     */
    public function record(string $date, array $customers, string $submissionKey): Collection
    {
        abort_unless(auth()->check(), 403);
        $validated = validator(
            ['date' => $date, 'customers' => $customers, 'submissionKey' => $submissionKey],
            [
                'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'submissionKey' => ['required', 'uuid'],
                'customers' => ['required', 'array', 'list', 'min:1', 'max:50'],
                'customers.*' => ['array:key,customer_id,customer_type,name,phone,method,reference,services'],
                'customers.*.key' => ['required', 'uuid'],
                'customers.*.customer_type' => ['nullable', Rule::in(['retail', 'package'])],
                'customers.*.customer_id' => ['required', 'string'],
                'customers.*.name' => ['nullable', 'string', 'max:120'],
                'customers.*.phone' => ['nullable', 'string', 'max:32'],
                'customers.*.method' => ['required', Rule::in(['cash', 'vietqr', 'pos'])],
                'customers.*.reference' => ['nullable', 'string', 'max:120', 'required_unless:customers.*.method,cash'],
                'customers.*.services' => ['required', 'array', 'list', 'min:1', 'max:10'],
                'customers.*.services.*' => ['array:key,offering_id,employee_id,amount'],
                'customers.*.services.*.key' => ['required', 'uuid'],
                'customers.*.services.*.offering_id' => ['required', 'integer', Rule::exists('offerings', 'id')->whereIn('type', ['service', 'package'])->where('status', 'active')],
                'customers.*.services.*.employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('status', 'active')->whereNull('deleted_at')],
                'customers.*.services.*.amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            ],
            [
                'required' => 'Vui lòng nhập :attribute.',
                'required_unless' => 'Vui lòng nhập mã giao dịch cho VietQR hoặc POS.',
                'integer' => ':attribute phải là số nguyên.',
                'min' => ':attribute phải có giá trị tối thiểu :min.',
                'max' => ':attribute vượt quá giới hạn :max.',
                'date_format' => 'Ngày ghi nhận phải có định dạng ngày hợp lệ.',
                'before_or_equal' => 'Không thể ghi nhận doanh thu cho ngày trong tương lai.',
                'exists' => ':attribute không còn hoạt động hoặc không hợp lệ. Vui lòng chọn lại.',
                'in' => 'Phương thức thanh toán không hợp lệ.',
            ],
            [
                'date' => 'ngày ghi nhận', 'customers' => 'danh sách khách',
                'customers.*.customer_id' => 'khách hàng', 'customers.*.name' => 'tên khách',
                'customers.*.phone' => 'số điện thoại', 'customers.*.reference' => 'mã giao dịch',
                'customers.*.services' => 'dịch vụ',
                'customers.*.services.*.offering_id' => 'dịch vụ',
                'customers.*.services.*.employee_id' => 'nhân viên',
                'customers.*.services.*.amount' => 'tiền thực thu',
            ],
        )->validate();

        return DB::transaction(function () use ($validated): Collection {
            User::query()->lockForUpdate()->findOrFail(auth()->id());
            $prefix = $validated['submissionKey'].'-';
            $existing = Invoice::query()->where('daily_entry_key', 'like', $prefix.'%')->get();
            if ($existing->isNotEmpty()) {
                abort_unless($existing->every(fn (Invoice $invoice): bool => (int) $invoice->created_by === (int) auth()->id()), 403);

                return $existing;
            }
            if (PayrollPeriod::query()->where('status', 'locked')->whereDate('period_start', '<=', $validated['date'])->whereDate('period_end', '>=', $validated['date'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['date' => 'Ngày này thuộc kỳ lương đã khóa. Không thể ghi thêm doanh thu và hoa hồng.']);
            }
            $occurredAt = CarbonImmutable::parse($validated['date'])->setTimeFrom(CarbonImmutable::now());
            $invoices = new Collection;
            $newPhones = [];
            foreach ($validated['customers'] as $customerIndex => $row) {
                if ($row['customer_id'] === 'new') {
                    if (blank($row['name'] ?? null) || blank($row['phone'] ?? null)) {
                        throw ValidationException::withMessages([
                            "customers.$customerIndex.name" => 'Khách mới cần có tên và số điện thoại.',
                            "customers.$customerIndex.phone" => 'Khách mới cần có tên và số điện thoại.',
                        ]);
                    }
                    $phone = trim($row['phone']);
                    if (in_array($phone, $newPhones, true) || Customer::withTrashed()->where('phone', $phone)->exists()) {
                        throw ValidationException::withMessages(["customers.$customerIndex.phone" => 'Số điện thoại đã có trong hệ thống hoặc danh sách nhập. Hãy chọn khách hiện có hoặc gộp dịch vụ vào cùng một khách.']);
                    }
                    $newPhones[] = $phone;
                    $customer = Customer::query()->create([
                        'code' => 'KH-'.Str::upper(Str::random(12)),
                        'name' => trim($row['name']), 'phone' => $phone,
                        'membership_tier' => 'Thành viên', 'status' => 'active', 'total_spend' => 0,
                    ]);
                    app(AuditService::class)->record('customer.created', $customer, null, $customer->toArray());
                } else {
                    $customer = Customer::query()->where('status', 'active')->whereKey($row['customer_id'])->first();
                    if ($customer === null) {
                        throw ValidationException::withMessages(["customers.$customerIndex.customer_id" => 'Khách hàng không còn hoạt động. Vui lòng chọn lại.']);
                    }
                }
                $isPackageCustomer = ($row['customer_type'] ?? 'retail') === 'package';
                foreach ($row['services'] as $serviceIndex => $service) {
                    $offering = Offering::query()->findOrFail($service['offering_id']);
                    $amount = (int) $service['amount'];
                    $invoice = Invoice::query()->create([
                        'customer_id' => $customer->id, 'created_by' => auth()->id(),
                        'code' => 'HD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
                        'daily_entry_key' => $prefix.$customerIndex.'-'.$serviceIndex,
                        'type' => 'service', 'status' => 'pending', 'subtotal' => $amount,
                        'discount_amount' => 0, 'total_received' => 0, 'issued_at' => $occurredAt,
                        'notes' => $isPackageCustomer
                            ? 'Ca phục vụ khách làm gói (nhập cuối ngày).'
                            : 'Ghi nhận từ màn hình nhập cuối ngày.',
                    ]);
                    $invoice->items()->create([
                        'offering_id' => $offering->id, 'employee_id' => $service['employee_id'],
                        'description' => $offering->name, 'quantity' => 1,
                        'unit_price' => $amount, 'line_total' => $amount, 'package_sessions' => 0,
                    ]);
                    app(AuditService::class)->record('invoice.created', $invoice, null, $invoice->toArray());
                    app(CheckoutService::class)->pay($invoice, $row['method'], $row['reference'] ?? null, $occurredAt);

                    if ($isPackageCustomer) {
                        $activePackage = CustomerPackage::query()
                            ->where('customer_id', $customer->id)
                            ->where('offering_id', $offering->id)
                            ->where('status', 'active')
                            ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', $occurredAt->toDateString()))
                            ->whereColumn('sessions_used', '<', 'sessions_total')
                            ->first();

                        if ($activePackage !== null) {
                            app(PackageUsageService::class)->consume(
                                $activePackage,
                                null,
                                (int) $service['employee_id'],
                                'Trừ buổi ca làm cuối ngày ('.$invoice->code.')'
                            );
                            $invoice->update(['customer_package_id' => $activePackage->id]);
                        }
                    }

                    $invoices->push($invoice->fresh());
                }
            }

            return $invoices;
        }, 3);
    }
}
