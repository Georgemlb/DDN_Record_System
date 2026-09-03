<?php

use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::resource('transactions', TransactionController::class);
    Route::resource('expenses', ExpenseController::class);
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');

    Route::get('configurations', [ConfigurationController::class, 'index'])->name('configurations.index');
    Route::prefix('configurations')->name('configurations.')->group(function () {
        Route::resource('baggage-options', \App\Http\Controllers\Configuration\BaggageOptionController::class)->only(['store', 'update', 'destroy']);
        Route::resource('online-assistance-types', \App\Http\Controllers\Configuration\OnlineAssistanceTypeController::class)->only(['store', 'update', 'destroy']);
        Route::resource('expense-categories', \App\Http\Controllers\Configuration\ExpenseCategoryController::class)->only(['store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';
