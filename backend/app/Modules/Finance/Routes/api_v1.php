<?php

use App\Modules\Finance\Controllers\Api\V1\AccountMappingController;
use App\Modules\Finance\Controllers\Api\V1\BankReconciliationController;
use App\Modules\Finance\Controllers\Api\V1\CoaController;
use App\Modules\Finance\Controllers\Api\V1\FiscalPeriodController;
use App\Modules\Finance\Controllers\Api\V1\InvoiceController;
use App\Modules\Finance\Controllers\Api\V1\JournalController;
use App\Modules\Finance\Controllers\Api\V1\PaymentController;
use App\Modules\Finance\Controllers\Api\V1\ReportController;
use App\Modules\Finance\Controllers\Api\V1\TaxController;
use Illuminate\Support\Facades\Route;

Route::prefix('finance')->name('finance.')->middleware(['auth:api', 'company.context'])->group(function (): void {
    Route::prefix('coa')->name('coa.')->group(function (): void {
        Route::get('/', [CoaController::class, 'index'])->name('index');
        Route::get('/tree', [CoaController::class, 'tree'])->name('tree');
        Route::post('/', [CoaController::class, 'store'])->name('store');
        Route::put('/{publicId}', [CoaController::class, 'update'])->name('update');
    });

    Route::get('/fiscal-periods', [FiscalPeriodController::class, 'index'])->name('fiscal-periods.index');
    Route::post('/fiscal-periods/{year}/{month}/close', [FiscalPeriodController::class, 'close'])->name('fiscal-periods.close');
    Route::post('/fiscal-periods/{year}/{month}/lock', [FiscalPeriodController::class, 'lock'])->name('fiscal-periods.lock');
    Route::post('/fiscal-periods/{year}/{month}/reopen', [FiscalPeriodController::class, 'reopen'])->name('fiscal-periods.reopen');

    Route::prefix('account-mappings')->name('account-mappings.')->group(function (): void {
        Route::get('/', [AccountMappingController::class, 'index'])->name('index');
        Route::put('/{mappingKey}', [AccountMappingController::class, 'update'])->name('update');
    });

    Route::prefix('journals')->name('journals.')->group(function (): void {
        Route::get('/', [JournalController::class, 'index'])->name('index');
        Route::post('/', [JournalController::class, 'store'])->name('store');
        Route::get('/{publicId}', [JournalController::class, 'show'])->name('show');
        Route::post('/{publicId}/post', [JournalController::class, 'post'])->name('post');
        Route::post('/{publicId}/void', [JournalController::class, 'void'])->name('void');
    });

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/ar-aging', [ReportController::class, 'arAging'])->name('ar-aging');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/ap-aging', [ReportController::class, 'apAging'])->name('ap-aging');
    });

    Route::prefix('invoices')->name('invoices.')->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::get('/{publicId}', [InvoiceController::class, 'show'])->name('show');
        Route::put('/{publicId}', [InvoiceController::class, 'update'])->name('update');
        Route::post('/{publicId}/post', [InvoiceController::class, 'post'])->name('post');
    });

    Route::prefix('bank-reconciliation')->name('bank-recon.')->group(function (): void {
        Route::get('/lines', [BankReconciliationController::class, 'index'])->name('lines.index');
        Route::post('/lines', [BankReconciliationController::class, 'store'])->name('lines.store');
        Route::get('/unmatched-payments', [BankReconciliationController::class, 'unmatchedPayments'])->name('unmatched-payments');
        Route::post('/lines/{linePublicId}/match/{paymentPublicId}', [BankReconciliationController::class, 'match'])->name('lines.match');
    });

    Route::prefix('payments')->name('payments.')->group(function (): void {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::get('/{publicId}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{publicId}/post', [PaymentController::class, 'post'])->name('post');
    });

    Route::prefix('tax')->name('tax.')->group(function (): void {
        Route::post('/ppn/calculate', [TaxController::class, 'calculatePpn'])->name('ppn.calculate');
        Route::get('/ppn/transactions', [TaxController::class, 'ppnTransactions'])->name('ppn.transactions');

        Route::get('/efaktur', [TaxController::class, 'efakturList'])->name('efaktur.index');
        Route::post('/efaktur/{ppnTransactionId}/request', [TaxController::class, 'requestEfaktur'])->name('efaktur.request');
        Route::post('/efaktur/{publicId}/approve', [TaxController::class, 'approveEfaktur'])->name('efaktur.approve');

        Route::get('/spt-ppn', [TaxController::class, 'sptList'])->name('spt.index');
        Route::get('/spt-ppn/{year}/{month}', [TaxController::class, 'sptShow'])->name('spt.show');
        Route::post('/spt-ppn/generate', [TaxController::class, 'sptGenerate'])->name('spt.generate');
        Route::post('/spt-ppn/finalize', [TaxController::class, 'sptFinalize'])->name('spt.finalize');

        Route::get('/pph23/transactions', [TaxController::class, 'pph23Transactions'])->name('pph23.transactions');
        Route::get('/ebupot', [TaxController::class, 'ebupotList'])->name('ebupot.index');
        Route::post('/ebupot/{pph23TransactionId}/issue', [TaxController::class, 'issueEbupot'])->name('ebupot.issue');
    });
});