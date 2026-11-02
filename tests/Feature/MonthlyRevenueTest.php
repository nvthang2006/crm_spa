<?php

namespace Tests\Feature;

use App\Livewire\Admin\DailyRevenuePage;
use App\Models\CommissionEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offering;
use App\Models\Payment;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_totals_include_both_boundaries_and_exclude_other_months_unpaid_and_reversed_entries(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create(['name' => 'Tuyết']);
        $other = Employee::factory()->create(['name' => 'Thúy']);
        $this->receipt($customer, $employee, '2026-09-01 00:00:00', 100000, 10000);
        $this->receipt($customer, $other, '2026-09-30 23:59:59', 200000, 30000, 'locked');
        $this->receipt($customer, $employee, '2026-08-31 23:59:59', 900000, 90000);
        $this->receipt($customer, $employee, '2026-10-01 00:00:00', 900000, 90000);
        Invoice::factory()->create(['customer_id' => $customer->id, 'status' => 'pending', 'paid_at' => '2026-09-02', 'total_received' => 900000]);
        CommissionEntry::factory()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-02', 'status' => 'reversed', 'amount' => 90000]);

        Livewire::test(DailyRevenuePage::class)->call('switchPeriod', 'month')->set('month', '2026-09')
            ->assertViewHas('summary', ['customers' => 1, 'revenue' => 300000, 'commission' => 40000])
            ->assertViewHas('days', ['2026-09-01' => ['customers' => 1, 'revenue' => 100000, 'commission' => 10000], '2026-09-30' => ['customers' => 1, 'revenue' => 200000, 'commission' => 30000]])
            ->assertViewHas('staff', fn (array $staff): bool => $staff[$employee->id]['commission'] === 10000 && $staff[$other->id]['commission'] === 30000)
            ->assertSee('tháng 09/2026')->call('viewDay', '2026-09-30')->assertSet('period', 'day')
            ->assertViewHas('summary', ['customers' => 1, 'revenue' => 200000, 'commission' => 30000]);
    }

    public function test_detail_filters_do_not_change_totals_and_historical_employee_and_customer_remain_visible(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create(['name' => 'A Thắng', 'phone' => '0901234567']);
        $employee = Employee::factory()->create(['name' => 'Tuyết']);
        $otherCustomer = Customer::factory()->create();
        $otherEmployee = Employee::factory()->create();
        $invoice = $this->receipt($customer, $employee, '2026-09-02 12:00:00', 100000, 10000);
        $this->receipt($otherCustomer, $otherEmployee, '2026-09-03 12:00:00', 200000, 20000);
        $customer->delete();
        $employee->delete();

        $component = Livewire::test(DailyRevenuePage::class)->call('switchPeriod', 'month')->set('month', '2026-09')
            ->set('search', '090123')->set('employeeFilter', (string) $employee->id)
            ->assertViewHas('summary', ['customers' => 2, 'revenue' => 300000, 'commission' => 30000])
            ->assertViewHas('details', fn (array $rows): bool => count($rows) === 1 && $rows[0]['invoice']->id === $invoice->id && $rows[0]['commissions']->sum('amount') === 10000)
            ->assertSee('A Thắng')->assertSee('Tuyết')->assertSee('0901234567')->assertSee('10%');
        $component->set('search', 'không tồn tại')->assertViewHas('details', [])
            ->assertViewHas('summary', ['customers' => 2, 'revenue' => 300000, 'commission' => 30000]);
    }

    public function test_monthly_totals_cover_all_pages_and_refund_removes_revenue_and_commission(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        for ($i = 0; $i < 16; $i++) {
            $invoice = $this->receipt($customer, $employee, '2026-09-02 12:00:00', 100000, 10000);
        }
        $component = Livewire::test(DailyRevenuePage::class)->call('switchPeriod', 'month')->set('month', '2026-09')
            ->assertViewHas('summary', ['customers' => 1, 'revenue' => 1600000, 'commission' => 160000])
            ->assertViewHas('entries', fn ($entries): bool => $entries->total() === 16 && $entries->count() === 15);
        $component->call('nextPage')->assertViewHas('details', fn (array $rows): bool => count($rows) === 1)
            ->set('search', $customer->name)->assertSet('paginators.page', 1);

        app(RefundService::class)->refund($invoice, 'cash', 'Hoàn tiền kiểm tra');

        $component->call('$refresh')->assertViewHas('summary', ['customers' => 1, 'revenue' => 1500000, 'commission' => 150000]);
    }

    public function test_invalid_or_empty_month_does_not_return_unbounded_data(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(DailyRevenuePage::class)->call('switchPeriod', 'month')->set('month', '2026-99')
            ->assertHasErrors('month')->assertViewHas('summary', ['customers' => 0, 'revenue' => 0, 'commission' => 0])
            ->set('month', '')->assertHasErrors('month')->assertViewHas('details', []);
    }

    public function test_multi_item_receipt_allocates_actual_money_once_and_legacy_repeated_service_commission_is_not_duplicated(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $employee = Employee::factory()->create();
        $other = Employee::factory()->create();
        $invoice = $this->receipt($customer, $employee, '2026-09-02 12:00:00', 90000, 9000);
        $item = $invoice->items()->first();
        $item->update(['line_total' => 100000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $item->offering_id, 'employee_id' => $other->id, 'description' => $item->description, 'quantity' => 1, 'line_total' => 200000]);

        Livewire::test(DailyRevenuePage::class)->call('switchPeriod', 'month')->set('month', '2026-09')
            ->assertViewHas('summary', ['customers' => 1, 'revenue' => 90000, 'commission' => 9000])
            ->assertViewHas('details', fn (array $rows): bool => count($rows) === 2 && $rows[0]['amount'] === 30000 && $rows[1]['amount'] === 60000 && $rows[0]['commissions']->sum('amount') === 9000 && $rows[1]['commissions']->isEmpty())
            ->assertViewHas('staff', fn (array $staff): bool => $staff[$employee->id]['revenue'] === 30000 && $staff[$other->id]['revenue'] === 60000);
    }

    public function test_filter_by_commission_recipient_shows_the_service_even_when_another_employee_performed_it(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->actingAs(User::factory()->create());
        $customer = Customer::factory()->create();
        $performer = Employee::factory()->create();
        $recipient = Employee::factory()->create();
        $invoice = $this->receipt($customer, $performer, '2026-09-02 12:00:00', 100000, 10000);
        CommissionEntry::query()->where('invoice_id', $invoice->id)->update(['employee_id' => $recipient->id]);

        Livewire::test(DailyRevenuePage::class)->call('switchPeriod', 'month')->set('month', '2026-09')
            ->set('employeeFilter', (string) $recipient->id)
            ->assertViewHas('details', fn (array $rows): bool => count($rows) === 1 && $rows[0]['item']->employee_id === $performer->id && $rows[0]['commissions']->first()->employee_id === $recipient->id);
    }

    private function receipt(Customer $customer, Employee $employee, string $date, int $amount, int $commission, string $status = 'pending'): Invoice
    {
        $offering = Offering::factory()->create(['type' => 'service', 'name' => 'Nặn mụn']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'status' => 'paid', 'paid_at' => $date, 'total_received' => $amount, 'subtotal' => $amount]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $amount, 'paid_at' => $date]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'employee_id' => $employee->id, 'description' => $offering->name, 'quantity' => 1, 'unit_price' => $amount, 'line_total' => $amount]);
        CommissionEntry::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'employee_id' => $employee->id, 'occurred_at' => $date, 'amount' => $commission, 'status' => $status]);

        return $invoice;
    }
}
