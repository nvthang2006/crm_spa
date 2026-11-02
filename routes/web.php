<?php

use App\Http\Controllers\InvoicePrintController;
use App\Livewire\Admin\AppointmentsPage;
use App\Livewire\Admin\CustomersPage;
use App\Livewire\Admin\DailyRevenuePage;
use App\Livewire\Admin\DashboardPage;
use App\Livewire\Admin\EmployeesPage;
use App\Livewire\Admin\InvoicesPage;
use App\Livewire\Admin\PayrollPage;
use App\Livewire\Admin\RoomsPage;
use App\Livewire\Admin\ServicesPage;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardPage::class)->name('dashboard');
    Route::get('/nhap-cuoi-ngay', DailyRevenuePage::class)->name('daily-revenue');
    Route::get('/nhan-vien', EmployeesPage::class)->name('employees');
    Route::get('/bang-luong', PayrollPage::class)->name('payroll');
    Route::get('/dich-vu', ServicesPage::class)->name('services');
    Route::get('/lich-hen', AppointmentsPage::class)->name('appointments');
    Route::get('/phong-giuong', RoomsPage::class)->name('rooms');
    Route::get('/khach-hang', CustomersPage::class)->name('customers');
    Route::get('/hoa-don', InvoicesPage::class)->name('invoices');
    Route::get('/hoa-don/so-giao-ca', [InvoicePrintController::class, 'shiftReport'])->name('invoices.shift-report');
    Route::get('/hoa-don/{invoice}/in', InvoicePrintController::class)->name('invoices.print');
    Route::get('/ui-preview', function () {
        return view('admin.ui-preview');
    })->name('admin.ui-preview');
});
