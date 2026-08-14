<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoodController;
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

        Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);
        Route::resource('goods', GoodController::class)->except(['show', 'destroy']);
        Route::resource('invoices', InvoiceController::class)->except(['destroy']);
        Route::post('/invoices/send', [InvoiceWorkflowController::class, 'send'])->middleware('throttle:10,1')->name('invoices.send');
        Route::post('/invoices/{invoice}/confirm-demo', [InvoiceWorkflowController::class, 'confirm'])->name('invoices.confirm_demo');
        Route::post('/invoices/{invoice}/inquire', [InvoiceWorkflowController::class, 'inquire'])->middleware('throttle:20,1')->name('invoices.inquire');
        Route::patch('/invoices/{invoice}/buyer-status', [InvoiceWorkflowController::class, 'buyerStatus'])->name('invoices.buyer_status');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/moadian/test', TaxpayerConnectionController::class)->middleware('throttle:5,1')->name('profile.moadian.test');

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
        });
    });
});
