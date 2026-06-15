<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectCategoryController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// First-run admin setup via admin:invite token (no auth middleware).
Route::get('/setup',  [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Accounts — admin only
    Route::prefix('accounts')->name('accounts.')->middleware('role:admin')->group(function () {
        Route::get('/',                                   [AccountController::class, 'index'])->name('index');
        Route::get('/overall',                            [AccountController::class, 'overall'])->name('overall');

        // Entries (create / update / destroy)
        Route::post('/entries',                           [AccountController::class, 'storeEntry'])->name('entries.store');
        Route::put('/entries/{entry}',                    [AccountController::class, 'updateEntry'])->name('entries.update');
        Route::delete('/entries/{entry}',                 [AccountController::class, 'destroyEntry'])->name('entries.destroy');

        // Transfers (legacy index redirects to the central register; the inline
        // "New Transfer" widget on the Accounts page still posts here and stays
        // backwards compatible with all existing links).
        Route::get('/transfers', fn () => redirect()->route('transfers.index'))->name('transfers.index');
        Route::post('/transfers',                         [AccountController::class, 'storeTransfer'])->name('transfers.store');
        Route::delete('/transfers/{transfer}',            [AccountController::class, 'destroyTransfer'])->name('transfers.destroy');

        // Bank accounts (create / update)
        Route::post('/bank-accounts',                     [AccountController::class, 'storeBankAccount'])->name('bank-accounts.store');
        Route::put('/bank-accounts/{bankAccount}',        [AccountController::class, 'updateBankAccount'])->name('bank-accounts.update');

        Route::get('/{entitySlug}',                       [AccountController::class, 'entity'])->name('entity');
        Route::get('/{entitySlug}/{accountId}',           [AccountController::class, 'show'])->name('show');
    });

    // Project categories — admin + CFO
    Route::prefix('categories')->name('categories.')->middleware('role:admin,cfo')->group(function () {
        Route::get('/',              [ProjectCategoryController::class, 'index'])->name('index');
        Route::post('/',             [ProjectCategoryController::class, 'store'])->name('store');
        Route::put('/{category}',    [ProjectCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [ProjectCategoryController::class, 'destroy'])->name('destroy');
    });

    // Projects — admin + CFO
    Route::prefix('projects')->name('projects.')->middleware('role:admin,cfo')->group(function () {
        Route::get('/',                                             [ProjectController::class, 'index'])->name('index');
        Route::get('/external',                                     [ProjectController::class, 'external'])->name('external');
        Route::get('/in-house',                                     [ProjectController::class, 'inHouse'])->name('in_house');
        Route::post('/',                                            [ProjectController::class, 'store'])->name('store');
        Route::post('/{project}/image',                             [ProjectController::class, 'updateImage'])->name('image.update');
        Route::get('/{project}',                                    [ProjectController::class, 'show'])->name('show');
        // External-project sub-pages
        Route::get('/{project}/overview',                           [ProjectController::class, 'showOverview'])->name('show.overview');
        Route::get('/{project}/allocation',                         [ProjectController::class, 'showAllocation'])->name('show.allocation');
        Route::get('/{project}/inflow',                             [ProjectController::class, 'showInflow'])->name('show.inflow');
        Route::get('/{project}/outflow',                            [ProjectController::class, 'showOutflow'])->name('show.outflow');
        Route::get('/{project}/history',                            [ProjectController::class, 'showHistory'])->name('show.history');
        Route::get('/{project}/export-workbook',                    [ProjectController::class, 'exportWorkbook'])->name('exportWorkbook');
        Route::get('/{project}/export/{section}',                   [ProjectController::class, 'export'])->name('export');
        Route::put('/{project}',                                    [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}',                                 [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{project}/collections',                       [ProjectController::class, 'storeCollection'])->name('collections.store');
        Route::post('/{project}/funding',                           [ProjectController::class, 'storeFunding'])->name('funding.store');
        Route::delete('/collections/{collection}',                  [ProjectController::class, 'destroyCollection'])->name('collections.destroy');
        Route::delete('/expenses/{expense}',                        [ProjectController::class, 'destroyExpense'])->name('expenses.destroy');
    });
    // Vouchers / Disbursements — payables, payments & cash issuances
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::get('/',                          [VoucherController::class, 'index'])->name('index');
        Route::get('/payables',                  [VoucherController::class, 'payables'])->name('payables');
        Route::post('/',                         [VoucherController::class, 'store'])->name('store');
        Route::put('/{voucher}',                 [VoucherController::class, 'update'])->name('update');
        Route::delete('/{voucher}',              [VoucherController::class, 'destroy'])->name('destroy');
        Route::post('/{voucher}/cancel',         [VoucherController::class, 'cancel'])->name('cancel');
        Route::post('/{voucher}/reactivate',     [VoucherController::class, 'reactivate'])->name('reactivate');
        Route::post('/{voucher}/payments',       [VoucherController::class, 'storePayment'])->name('payments.store');
        Route::delete('/payments/{payment}',     [VoucherController::class, 'destroyPayment'])->name('payments.destroy');
        Route::post('/{voucher}/attachments',                  [VoucherController::class, 'storeAttachment'])->name('attachments.store');
        Route::get('/attachments/{attachment}/download',       [VoucherController::class, 'downloadAttachment'])->name('attachments.download');
        Route::delete('/attachments/{attachment}',             [VoucherController::class, 'destroyAttachment'])->name('attachments.destroy');
    });

    // Transfers / Intercompany — admin only
    Route::prefix('transfers')->name('transfers.')->middleware('role:admin')->group(function () {
        Route::get('/',              [TransferController::class, 'index'])->name('index');
        Route::post('/',             [TransferController::class, 'store'])->name('store');
        Route::put('/{transfer}',    [TransferController::class, 'update'])->name('update');
        Route::delete('/{transfer}', [TransferController::class, 'destroy'])->name('destroy');
    });

    Route::get('/budget', fn () => view('budget.index'))->name('budget');
    Route::get('/invoices', fn () => view('invoices.index'))->name('invoices');
    Route::get('/purchase-orders', fn () => view('purchase-orders.index'))->name('purchase-orders');
    Route::get('/payroll', fn () => view('payroll.index'))->name('payroll');

    // Reports module — see App\Http\Controllers\ReportController.
    // Note: the index is named simply `reports` so the existing sidebar
    // link (route('reports')) keeps working without changes.
    Route::get('/reports',                                [\App\Http\Controllers\ReportController::class, 'index'])->name('reports');
    Route::get('/reports/cash-outflow',                   [\App\Http\Controllers\ReportController::class, 'cashOutflow'])->name('reports.cashOutflow');
    Route::get('/reports/account-balances',               [\App\Http\Controllers\ReportController::class, 'accountBalances'])->name('reports.accountBalances')->middleware('role:admin');
    Route::get('/reports/transfers',                      [\App\Http\Controllers\ReportController::class, 'transfers'])->name('reports.transfers')->middleware('role:admin');
    Route::get('/reports/collections',                    [\App\Http\Controllers\ReportController::class, 'collections'])->name('reports.collections');
    Route::get('/reports/payables',                       [\App\Http\Controllers\ReportController::class, 'payables'])->name('reports.payables');
    Route::post('/reports/export/pdf',                    [\App\Http\Controllers\ReportController::class, 'exportPdf'])->name('reports.exportPdf');
    Route::post('/reports/export/excel',                  [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('reports.exportExcel');

    Route::get('/settings', fn () => view('settings.index'))->name('settings');
});

require __DIR__.'/auth.php';
