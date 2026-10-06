<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerIsolationController;
use App\Http\Controllers\CustomerLifecycleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\Settings\BillingSettingsController;
use App\Http\Controllers\Settings\BusinessSettingsController;
use App\Http\Controllers\Settings\MessageTemplateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::inertia('/', 'welcome')->name('home');

// Halaman admin. Middleware permission menjaga akses dasar tiap modul; aksi yang lebih spesifik
// dicek Policy lewat Form Request atau Gate::authorize, dan syarat status data di Action.
// Halaman publik (tagihan, isolir) dan webhook ada di routes/public.php dan routes/webhooks.php.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware(PermissionMiddleware::using(Permission::PackagesView))->group(function () {
        Route::resource('packages', PackageController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('packages/{package}/activate', [PackageController::class, 'activate'])->name('packages.activate');
        Route::post('packages/{package}/deactivate', [PackageController::class, 'deactivate'])->name('packages.deactivate');
    });

    Route::middleware(PermissionMiddleware::using(Permission::RoutersManage))->group(function () {
        Route::resource('routers', RouterController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('routers/{router}/test', [RouterController::class, 'testConnection'])->name('routers.test');
    });

    Route::middleware(PermissionMiddleware::using(Permission::CustomersView))->group(function () {
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/activate', [CustomerLifecycleController::class, 'activate'])->name('customers.activate');
        Route::post('customers/{customer}/terminate', [CustomerLifecycleController::class, 'terminate'])->name('customers.terminate');
        Route::post('customers/{customer}/reactivate', [CustomerLifecycleController::class, 'reactivate'])->name('customers.reactivate');
        Route::put('customers/{customer}/package', [CustomerLifecycleController::class, 'changePackage'])->name('customers.package');
        Route::post('customers/{customer}/isolate', [CustomerIsolationController::class, 'isolate'])->name('customers.isolate');
        Route::post('customers/{customer}/release', [CustomerIsolationController::class, 'release'])->name('customers.release');
    });

    Route::middleware(PermissionMiddleware::using(Permission::InvoicesView))->group(function () {
        Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/reissue', [InvoiceController::class, 'reissue'])->name('invoices.reissue');
        Route::post('invoices/{invoice}/resend', [InvoiceController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('invoices.resend');
        Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
    });

    Route::middleware(PermissionMiddleware::using(Permission::PaymentsView))->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::patch('payments/{payment}/review', [PaymentController::class, 'review'])->name('payments.review');
    });

    Route::middleware(PermissionMiddleware::using(Permission::UsersManage))->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
    });

    Route::middleware(PermissionMiddleware::using(Permission::SettingsManage))
        ->prefix('settings')
        ->name('settings.')
        ->group(function () {
            Route::get('business', [BusinessSettingsController::class, 'edit'])->name('business.edit');
            Route::put('business', [BusinessSettingsController::class, 'update'])->name('business.update');
            Route::get('billing', [BillingSettingsController::class, 'edit'])->name('billing.edit');
            Route::put('billing', [BillingSettingsController::class, 'update'])->name('billing.update');
            Route::get('message-templates', [MessageTemplateController::class, 'index'])->name('message-templates.index');
            Route::put('message-templates/{template}', [MessageTemplateController::class, 'update'])->name('message-templates.update');
        });

    Route::middleware(PermissionMiddleware::using(Permission::ReportsView))
        ->prefix('reports')
        ->name('reports.')
        ->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('outstanding', [ReportController::class, 'outstanding'])->name('outstanding');
            Route::get('export/payments', [ReportExportController::class, 'payments'])->name('export.payments');
            Route::get('export/outstanding', [ReportExportController::class, 'outstanding'])->name('export.outstanding');
            Route::get('export/revenue', [ReportExportController::class, 'revenue'])->name('export.revenue');
        });
});

require __DIR__.'/settings.php';
