<?php

use App\Http\Controllers\Configuration\AirlineMarkupController;
use App\Http\Controllers\Configuration\BaggageOptionController;
use App\Http\Controllers\Configuration\OnlineAssistanceTypeController;
use App\Http\Controllers\Configuration\PsaRateController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FlightPortalDepositController;
use App\Http\Controllers\GcashLedgerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('transactions/{transaction}/restore', [TransactionController::class, 'restore'])->name('transactions.restore')->withTrashed();
    Route::delete('transactions/{transaction}/force-delete', [TransactionController::class, 'forceDelete'])->name('transactions.forceDelete')->withTrashed();
    Route::post('transactions/{transaction}/payments', [TransactionController::class, 'storePayment'])->name('transactions.payments.store');
    Route::resource('transactions', TransactionController::class);

    Route::post('expenses/{expense}/restore', [ExpenseController::class, 'restore'])->name('expenses.restore')->withTrashed();
    Route::delete('expenses/{expense}/force-delete', [ExpenseController::class, 'forceDelete'])->name('expenses.forceDelete')->withTrashed();
    Route::post('expenses/{expense}/reimburse', [ExpenseController::class, 'reimburse'])->name('expenses.reimburse');
    Route::resource('expenses', ExpenseController::class);
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');

    Route::resource('users', UserController::class)->middleware('can:manage-users');

    Route::middleware('can:manage-flight-portal')->group(function () {
        Route::get('portal-deposits', [FlightPortalDepositController::class, 'index'])->name('portal-deposits.index');
        Route::post('portal-deposits/top-up', [FlightPortalDepositController::class, 'storeTopUp'])->name('portal-deposits.top-up');
        Route::post('portal-deposits/adjustment', [FlightPortalDepositController::class, 'storeAdjustment'])->name('portal-deposits.adjustment');
        Route::put('portal-deposits/{deposit}', [FlightPortalDepositController::class, 'update'])->name('portal-deposits.update');
        Route::post('portal-deposits/{deposit}/move', [FlightPortalDepositController::class, 'move'])->name('portal-deposits.move');
        Route::delete('portal-deposits/{deposit}', [FlightPortalDepositController::class, 'destroy'])->name('portal-deposits.destroy');

        // GCash Ledger
        Route::get('gcash-ledger', [GcashLedgerController::class, 'index'])->name('gcash-ledger.index');
        Route::post('gcash-ledger/adjustment', [GcashLedgerController::class, 'storeAdjustment'])->name('gcash-ledger.adjustment');
        Route::put('gcash-ledger/{entry}', [GcashLedgerController::class, 'update'])->name('gcash-ledger.update');
        Route::post('gcash-ledger/{entry}/reorder', [GcashLedgerController::class, 'reorder'])->name('gcash-ledger.reorder');
        Route::post('gcash-ledger/{entry}/move', [GcashLedgerController::class, 'move'])->name('gcash-ledger.move');
        Route::delete('gcash-ledger/{entry}', [GcashLedgerController::class, 'destroy'])->name('gcash-ledger.destroy');
    });

    Route::middleware('can:view-reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');
        Route::get('reports/settlements/preview', [ReportController::class, 'previewPastSettlement'])->name('reports.settlements.preview');
        Route::post('reports/settlements', [ReportController::class, 'startDay'])->name('reports.settlements.start');
        Route::post('reports/settlements/past', [ReportController::class, 'storePastSettlement'])->name('reports.settlements.store-past');
        Route::put('reports/settlements/{settlement}', [ReportController::class, 'update'])->name('reports.settlements.update');
        Route::put('reports/settlements/{settlement}/close', [ReportController::class, 'closeDay'])->name('reports.settlements.close');
        Route::put('reports/settlements/{settlement}/reopen', [ReportController::class, 'reopen'])->name('reports.settlements.reopen');
        Route::get('reports/settlements/{settlement}', [ReportController::class, 'showSlip'])->name('reports.settlements.show');
    });

    Route::middleware('can:manage-configurations')->group(function () {
        Route::get('configurations', [ConfigurationController::class, 'index'])->name('configurations.index');
        Route::prefix('configurations')->name('configurations.')->group(function () {
            Route::resource('baggage-options', BaggageOptionController::class)->only(['store', 'update', 'destroy']);
            Route::resource('airline-markups', AirlineMarkupController::class)->only(['store', 'update', 'destroy']);
            Route::resource('online-assistance-types', OnlineAssistanceTypeController::class)->only(['store', 'update', 'destroy']);
            Route::resource('expense-categories', App\Http\Controllers\Configuration\ExpenseCategoryController::class)->only(['store', 'update', 'destroy']);
            Route::resource('psa-rates', PsaRateController::class)->only(['store', 'update', 'destroy']);
        });
    });

    Route::redirect('settings/baggage-options', '/configurations?tab=baggage');
    Route::redirect('settings/online-assistance-types', '/configurations?tab=online-assistance');
    Route::redirect('settings/expense-categories', '/configurations?tab=expense-categories');
});
