<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_invoice_and_shift_report_print_routes_render_audit_friendly_documents(): void
    {
        $customer = Customer::factory()->create(['name' => 'Khách In Chứng Từ']);
        $offering = Offering::factory()->create(['name' => 'Massage kiểm thử', 'price' => 500000]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'code' => 'HD-PRINT-001', 'subtotal' => 500000, 'issued_at' => now()]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id, 'description' => $offering->name, 'unit_price' => 500000, 'line_total' => 500000]);

        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('HD-PRINT-001')->assertSee('PHIẾU THU');
        $this->get(route('invoices.shift-report', ['from' => today()->toDateString(), 'to' => today()->toDateString()]))->assertOk()->assertSee('Sổ giao ca')->assertSee('Doanh thu thuần');
    }

    public function test_module_search_queries_are_forwarded_to_customer_appointment_and_invoice_pages(): void
    {
        $customer = Customer::factory()->create(['code' => 'KH-SEARCH-001', 'name' => 'Khách Tìm Kiếm']);
        $offering = Offering::factory()->create();
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'code' => 'LH-SEARCH-001', 'appointment_date' => today()]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'code' => 'HD-SEARCH-001']);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id]);

        $this->get(route('customers', ['search' => 'KH-SEARCH-001']))->assertOk()->assertSee('KH-SEARCH-001');
        $this->get(route('appointments', ['date' => today()->toDateString(), 'search' => 'LH-SEARCH-001']))->assertOk()->assertSee('LH-SEARCH-001');
        $this->get(route('invoices', ['search' => 'HD-SEARCH-001']))->assertOk()->assertSee('HD-SEARCH-001');
    }
}
