<?php

use App\Http\Controllers\Admin\StuffCatalogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataExchangeController;
use App\Http\Controllers\GoodController;
use App\Http\Controllers\InvoiceAdjustmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceWorkflowController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaxpayerConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::view('/license-expired', 'auth.license-expired')->name('license.expired');

    Route::middleware('license')->group(function () {
        Route::redirect('/', '/dashboard');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::middleware('tax-operator')->group(function () {
            Route::get('/customers/export', [DataExchangeController::class, 'exportCustomers'])->name('customers.export');
            Route::get('/customers/import-template', [DataExchangeController::class, 'customerTemplate'])->name('customers.template');
            Route::post('/customers/import', [DataExchangeController::class, 'importCustomers'])->name('customers.import');
            Route::resource('customers', CustomerController::class)->except(['show']);
            Route::get('/goods/export', [DataExchangeController::class, 'exportGoods'])->name('goods.export');
            Route::get('/goods/import-template', [DataExchangeController::class, 'goodTemplate'])->name('goods.template');
            Route::post('/goods/import', [DataExchangeController::class, 'importGoods'])->name('goods.import');
            Route::resource('goods', GoodController::class)->except(['show']);
            Route::get('/invoices/export', [DataExchangeController::class, 'exportInvoices'])->name('invoices.export');
            Route::get('/invoices/import-template', [DataExchangeController::class, 'invoiceTemplate'])->name('invoices.template');
            Route::post('/invoices/import', [DataExchangeController::class, 'importInvoices'])->name('invoices.import');
            Route::post('/invoices/moadian-sales-report', [DataExchangeController::class, 'importMoadianSalesReport'])->name('invoices.moadian-sales-report.import');
            Route::resource('invoices', InvoiceController::class);
            Route::post('/invoices/{invoice}/correction', [InvoiceAdjustmentController::class, 'correction'])->name('invoices.correction');
            Route::post('/invoices/{invoice}/cancellation', [InvoiceAdjustmentController::class, 'cancellation'])->name('invoices.cancellation');
            Route::post('/invoices/send', [InvoiceWorkflowController::class, 'send'])->middleware('throttle:10,1')->name('invoices.send');
            Route::post('/invoices/{invoice}/confirm-demo', [InvoiceWorkflowController::class, 'confirm'])->name('invoices.confirm_demo');
            Route::post('/invoices/{invoice}/inquire', [InvoiceWorkflowController::class, 'inquire'])->middleware('throttle:20,1')->name('invoices.inquire');
        });

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/moadian/test', TaxpayerConnectionController::class)->middleware(['tax-operator', 'throttle:5,1'])->name('profile.moadian.test');

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/stuff-catalog', [StuffCatalogController::class, 'index'])->name('stuff-catalog.index');
            Route::post('/stuff-catalog', [StuffCatalogController::class, 'store'])->middleware('throttle:3,1')->name('stuff-catalog.store');
            Route::get('/stuff-catalog/imports/{stuffCatalogImport}', [StuffCatalogController::class, 'show'])->name('stuff-catalog.imports.show');
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
        });
    });
});
