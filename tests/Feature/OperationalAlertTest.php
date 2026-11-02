<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Offering;
use App\Models\ServiceResource;
use App\Models\User;
use App\Services\OperationalAlertService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spa.resource_management_enabled' => true]);
        $this->actingAs(User::factory()->create());
        Carbon::setTestNow('2026-09-14 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operational_alerts_use_the_agreed_thresholds_and_are_user_scoped(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create(['price' => 500000]);
        Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'appointment_date' => today(), 'start_time' => '10:30', 'end_time' => '11:30', 'status' => 'scheduled']);
        Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'appointment_date' => today(), 'start_time' => '12:30', 'end_time' => '13:30', 'status' => 'scheduled']);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'subtotal' => 500000, 'status' => 'pending']);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'offering_id' => $offering->id]);
        ServiceResource::factory()->create(['status' => 'cleaning']);
        ServiceResource::factory()->create(['status' => 'maintenance']);
        CustomerPackage::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'expires_on' => today()->addDays(3), 'sessions_total' => 10, 'sessions_used' => 1, 'status' => 'active']);
        CustomerPackage::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'expires_on' => today()->addDays(8), 'sessions_total' => 10, 'sessions_used' => 1, 'status' => 'active']);

        $user = auth()->user();
        $alerts = app(OperationalAlertService::class)->forUser($user);

        $this->assertCount(5, $alerts);
        $this->assertSame(5, app(OperationalAlertService::class)->unreadCount($user));
        $this->assertSame(1, $alerts->where('type', 'appointment_due')->count());
        $this->assertSame(1, $alerts->where('type', 'invoice_pending')->count());
        $this->assertSame(1, $alerts->where('type', 'package_expiring')->count());
        $this->assertSame(2, $alerts->whereIn('type', ['resource_cleaning', 'resource_maintenance'])->count());

        app(OperationalAlertService::class)->markRead($user, $alerts->first()->id);
        $this->assertSame(4, app(OperationalAlertService::class)->unreadCount($user));

        $otherUser = User::factory()->create();
        $this->assertSame(5, app(OperationalAlertService::class)->unreadCount($otherUser));
        app(OperationalAlertService::class)->markAllRead($user);
        $this->assertSame(0, app(OperationalAlertService::class)->unreadCount($user));
    }

    public function test_resolved_operational_alerts_are_deactivated_without_deleting_history(): void
    {
        $customer = Customer::factory()->create();
        $offering = Offering::factory()->create();
        $appointment = Appointment::factory()->create(['customer_id' => $customer->id, 'offering_id' => $offering->id, 'appointment_date' => today(), 'start_time' => '10:30', 'end_time' => '11:30', 'status' => 'scheduled']);
        $alertService = app(OperationalAlertService::class);
        $alertService->sync();
        $this->assertDatabaseHas('operational_alerts', ['alert_key' => 'appointment-'.$appointment->id, 'is_active' => 1]);

        $appointment->update(['status' => 'arrived']);
        $alertService->sync();

        $this->assertDatabaseHas('operational_alerts', ['alert_key' => 'appointment-'.$appointment->id, 'is_active' => 0]);
        $this->assertDatabaseCount('operational_alerts', 1);
    }
}
