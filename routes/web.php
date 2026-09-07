<?php

use App\Http\Controllers\Configuration\BaggageOptionController;
use App\Http\Controllers\Configuration\OnlineAssistanceTypeController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FlightPortalDepositController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('transactions/{transaction}/restore', [TransactionController::class, 'restore'])->name('transactions.restore')->withTrashed();
    Route::delete('transactions/{transaction}/force-delete', [TransactionController::class, 'forceDelete'])->name('transactions.forceDelete')->withTrashed();
    Route::resource('transactions', TransactionController::class);

    Route::post('expenses/{expense}/restore', [ExpenseController::class, 'restore'])->name('expenses.restore')->withTrashed();
    Route::delete('expenses/{expense}/force-delete', [ExpenseController::class, 'forceDelete'])->name('expenses.forceDelete')->withTrashed();
    Route::resource('expenses', ExpenseController::class);
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');

    Route::get('portal-deposits', [FlightPortalDepositController::class, 'index'])->name('portal-deposits.index');
    Route::post('portal-deposits/top-up', [FlightPortalDepositController::class, 'storeTopUp'])->name('portal-deposits.top-up');
    Route::post('portal-deposits/adjustment', [FlightPortalDepositController::class, 'storeAdjustment'])->name('portal-deposits.adjustment');
    Route::delete('portal-deposits/{deposit}', [FlightPortalDepositController::class, 'destroy'])->name('portal-deposits.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');
    Route::post('reports/settlements', [ReportController::class, 'startDay'])->name('reports.settlements.start');
    Route::put('reports/settlements/{settlement}/close', [ReportController::class, 'closeDay'])->name('reports.settlements.close');
    Route::put('reports/settlements/{settlement}/reopen', [ReportController::class, 'reopen'])->name('reports.settlements.reopen');
    Route::get('reports/settlements/{settlement}', [ReportController::class, 'showSlip'])->name('reports.settlements.show');

    Route::get('configurations', [ConfigurationController::class, 'index'])->name('configurations.index');
    Route::prefix('configurations')->name('configurations.')->group(function () {
        Route::resource('baggage-options', BaggageOptionController::class)->only(['store', 'update', 'destroy']);
        Route::resource('online-assistance-types', OnlineAssistanceTypeController::class)->only(['store', 'update', 'destroy']);
        Route::resource('expense-categories', App\Http\Controllers\Configuration\ExpenseCategoryController::class)->only(['store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';
