<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('job_title');
            $table->string('shift_name')->nullable();
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date')->index();
            $table->string('shift_name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('status', 24)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('attendance_date')->index();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('status', 24)->default('present');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('offerings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 16)->default('service')->index();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedInteger('sessions_total')->default(1);
            $table->string('status', 24)->default('active')->index();
            $table->boolean('featured')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('beneficiary_type', 32)->default('primary_technician');
            $table->string('basis', 24)->default('completion');
            $table->string('calculation_type', 16)->default('percentage');
            $table->decimal('value', 12, 2)->default(0);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['offering_id', 'starts_on', 'ends_on']);
        });

        Schema::create('resource_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('floor')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_area_id')->constrained()->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('resource_type', 16)->default('bed');
            $table->string('status', 24)->default('ready')->index();
            $table->text('maintenance_note')->nullable();
            $table->dateTime('maintenance_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('phone', 32)->index();
            $table->string('email')->nullable();
            $table->string('membership_tier', 24)->default('Thành viên');
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->unsignedBigInteger('total_spend')->default(0);
            $table->dateTime('last_visit_at')->nullable()->index();
            $table->string('status', 24)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_health_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('preferred_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('allergy_notes')->nullable();
            $table->text('current_condition')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('care_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('offering_id')->constrained()->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->date('purchased_on');
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('sessions_total');
            $table->unsignedInteger('sessions_used')->default(0);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('offering_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('support_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('service_resource_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->date('appointment_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 32)->default('scheduled')->index();
            $table->string('source', 16)->default('booked');
            $table->string('payment_type', 16)->default('service');
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('override_conflict_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['appointment_date', 'start_time', 'status']);
        });

        Schema::create('package_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_package_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('session_number');
            $table->string('direction', 16)->default('use');
            $table->string('verification_method', 32)->default('admin_confirmation');
            $table->text('verification_note')->nullable();
            $table->dateTime('used_at');
            $table->timestamps();
            $table->unique(['customer_package_id', 'session_number', 'direction']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('type', 16)->default('service');
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_received')->default(0);
            $table->string('payment_method', 16)->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->unsignedInteger('package_sessions')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_code', 40)->unique();
            $table->string('method', 16);
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('completed');
            $table->string('reference')->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();
        });

        Schema::create('commission_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('basis', 24);
            $table->string('calculation_type', 16);
            $table->decimal('rate', 12, 2)->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['employee_id', 'occurred_at']);
        });

        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 16)->default('draft')->index();
            $table->unsignedBigInteger('total_base_salary')->default(0);
            $table->unsignedBigInteger('total_commission')->default(0);
            $table->unsignedBigInteger('total_net_pay')->default(0);
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedInteger('work_days')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('tips')->default(0);
            $table->unsignedBigInteger('deductions')->default(0);
            $table->unsignedBigInteger('net_pay')->default(0);
            $table->string('status', 16)->default('draft');
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount');
            $table->string('status', 16)->default('pending')->index();
            $table->text('reason');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->nullableMorphs('subject');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->dateTime('created_at');
            $table->index(['created_at', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('package_usages');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('customer_packages');
        Schema::dropIfExists('customer_health_profiles');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('service_resources');
        Schema::dropIfExists('resource_areas');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('offerings');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('employees');
    }
};
