<?php

use App\Modules\Business\Controllers\Api\V1\BusinessReportController;
use App\Modules\Business\Controllers\Api\V1\CrmAccountController;
use App\Modules\Business\Controllers\Api\V1\InventoryController;
use App\Modules\Business\Controllers\Api\V1\AttendanceController;
use App\Modules\Business\Controllers\Api\V1\HrController;
use App\Modules\Business\Controllers\Api\V1\LeaveController;
use App\Modules\Business\Controllers\Api\V1\PayrollController;
use App\Modules\Business\Controllers\Api\V1\ProjectController;
use App\Modules\Business\Controllers\Api\V1\PurchasingController;
use App\Modules\Business\Controllers\Api\V1\QuotationController;
use App\Modules\Business\Controllers\Api\V1\TicketController;
use App\Modules\Business\Controllers\Api\V1\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'company.context'])->group(function (): void {
    Route::prefix('crm')->name('crm.')->group(function (): void {
        Route::prefix('accounts')->name('accounts.')->group(function (): void {
            Route::get('/', [CrmAccountController::class, 'index'])->name('index');
            Route::post('/', [CrmAccountController::class, 'store'])->name('store');
            Route::get('/{publicId}', [CrmAccountController::class, 'show'])->name('show');
            Route::put('/{publicId}', [CrmAccountController::class, 'update'])->name('update');
            Route::delete('/{publicId}', [CrmAccountController::class, 'destroy'])->name('destroy');
            Route::get('/{publicId}/contacts', [CrmAccountController::class, 'contacts'])->name('contacts');
            Route::post('/{publicId}/contacts', [CrmAccountController::class, 'storeContact'])->name('contacts.store');
        });
    });

    Route::prefix('projects')->name('projects.')->group(function (): void {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{publicId}/budget-summary', [ProjectController::class, 'budgetSummary'])->name('budget-summary');
        Route::get('/{publicId}/invoices', [ProjectController::class, 'invoices'])->name('invoices');
        Route::get('/{publicId}/time-entries', [ProjectController::class, 'timeEntries'])->name('time-entries.index');
        Route::post('/{publicId}/time-entries', [ProjectController::class, 'storeTimeEntry'])->name('time-entries.store');
        Route::put('/{publicId}/time-entries/{entryPublicId}', [ProjectController::class, 'updateTimeEntry'])->name('time-entries.update');
        Route::delete('/{publicId}/time-entries/{entryPublicId}', [ProjectController::class, 'destroyTimeEntry'])->name('time-entries.destroy');
        Route::get('/{publicId}/milestones', [ProjectController::class, 'milestones'])->name('milestones.index');
        Route::post('/{publicId}/milestones', [ProjectController::class, 'storeMilestone'])->name('milestones.store');
        Route::put('/{publicId}/milestones/{milestonePublicId}', [ProjectController::class, 'updateMilestone'])->name('milestones.update');
        Route::delete('/{publicId}/milestones/{milestonePublicId}', [ProjectController::class, 'destroyMilestone'])->name('milestones.destroy');
        Route::post('/{publicId}/milestones/{milestonePublicId}/invoice', [ProjectController::class, 'invoiceMilestone'])->name('milestones.invoice');
        Route::get('/{publicId}', [ProjectController::class, 'show'])->name('show');
        Route::put('/{publicId}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{publicId}', [ProjectController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('sales')->name('sales.')->group(function (): void {
        Route::prefix('quotations')->name('quotations.')->group(function (): void {
            Route::get('/', [QuotationController::class, 'index'])->name('index');
            Route::post('/', [QuotationController::class, 'store'])->name('store');
            Route::get('/{publicId}', [QuotationController::class, 'show'])->name('show');
            Route::put('/{publicId}', [QuotationController::class, 'update'])->name('update');
            Route::delete('/{publicId}', [QuotationController::class, 'destroy'])->name('destroy');
            Route::post('/{publicId}/send', [QuotationController::class, 'send'])->name('send');
            Route::post('/{publicId}/accept', [QuotationController::class, 'accept'])->name('accept');
        });
    });

    Route::prefix('ops')->name('ops.')->group(function (): void {
        Route::prefix('tickets')->name('tickets.')->group(function (): void {
            Route::get('/', [TicketController::class, 'index'])->name('index');
            Route::post('/', [TicketController::class, 'store'])->name('store');
            Route::get('/{publicId}', [TicketController::class, 'show'])->name('show');
            Route::put('/{publicId}', [TicketController::class, 'update'])->name('update');
            Route::delete('/{publicId}', [TicketController::class, 'destroy'])->name('destroy');
            Route::post('/{publicId}/assign', [TicketController::class, 'assign'])->name('assign');
            Route::post('/{publicId}/resolve', [TicketController::class, 'resolve'])->name('resolve');
            Route::post('/{publicId}/close', [TicketController::class, 'close'])->name('close');
        });

        Route::prefix('work-orders')->name('work-orders.')->group(function (): void {
            Route::get('/', [WorkOrderController::class, 'index'])->name('index');
            Route::post('/', [WorkOrderController::class, 'store'])->name('store');
            Route::get('/{publicId}', [WorkOrderController::class, 'show'])->name('show');
            Route::put('/{publicId}', [WorkOrderController::class, 'update'])->name('update');
            Route::delete('/{publicId}', [WorkOrderController::class, 'destroy'])->name('destroy');
            Route::post('/{publicId}/assign', [WorkOrderController::class, 'assign'])->name('assign');
            Route::post('/{publicId}/complete', [WorkOrderController::class, 'complete'])->name('complete');
        });
    });

    Route::prefix('inventory')->name('inventory.')->group(function (): void {
        Route::get('/items', [InventoryController::class, 'items'])->name('items.index');
        Route::post('/items', [InventoryController::class, 'storeItem'])->name('items.store');
        Route::get('/items/{publicId}', [InventoryController::class, 'showItem'])->name('items.show');
        Route::put('/items/{publicId}', [InventoryController::class, 'updateItem'])->name('items.update');
        Route::delete('/items/{publicId}', [InventoryController::class, 'destroyItem'])->name('items.destroy');

        Route::get('/warehouses', [InventoryController::class, 'warehouses'])->name('warehouses.index');
        Route::post('/warehouses', [InventoryController::class, 'storeWarehouse'])->name('warehouses.store');
        Route::get('/warehouses/{publicId}', [InventoryController::class, 'showWarehouse'])->name('warehouses.show');
        Route::put('/warehouses/{publicId}', [InventoryController::class, 'updateWarehouse'])->name('warehouses.update');
        Route::delete('/warehouses/{publicId}', [InventoryController::class, 'destroyWarehouse'])->name('warehouses.destroy');

        Route::get('/balances', [InventoryController::class, 'balances'])->name('balances.index');
        Route::get('/movements', [InventoryController::class, 'movements'])->name('movements.index');
        Route::post('/movements', [InventoryController::class, 'storeMovement'])->name('movements.store');
    });

    Route::prefix('purchasing')->name('purchasing.')->group(function (): void {
        Route::prefix('orders')->name('orders.')->group(function (): void {
            Route::get('/', [PurchasingController::class, 'index'])->name('index');
            Route::post('/', [PurchasingController::class, 'store'])->name('store');
            Route::get('/{publicId}', [PurchasingController::class, 'show'])->name('show');
            Route::put('/{publicId}', [PurchasingController::class, 'update'])->name('update');
            Route::delete('/{publicId}', [PurchasingController::class, 'destroy'])->name('destroy');
            Route::post('/{publicId}/submit', [PurchasingController::class, 'submit'])->name('submit');
            Route::post('/{publicId}/approve', [PurchasingController::class, 'approve'])->name('approve');
            Route::post('/{publicId}/receive', [PurchasingController::class, 'receive'])->name('receive');
        });
    });

    Route::prefix('hr')->name('hr.')->group(function (): void {
        Route::get('/summary', [HrController::class, 'summary'])->name('summary');
        Route::get('/me', [HrController::class, 'me'])->name('me');
        Route::get('/me/payslips', [HrController::class, 'myPayslips'])->name('me.payslips');
        Route::get('/me/payslips/{runPublicId}', [HrController::class, 'myPayslip'])->name('me.payslip');
        Route::get('/me/leave-balance', [HrController::class, 'myLeaveBalance'])->name('me.leave-balance');
        Route::get('/settings', [HrController::class, 'settings'])->name('settings');
        Route::put('/settings', [HrController::class, 'updateSettings'])->name('settings.update');

        Route::get('/employees', [PayrollController::class, 'employees'])->name('employees.index');
        Route::post('/employees', [PayrollController::class, 'storeEmployee'])->name('employees.store');
        Route::put('/employees/device-pins', [PayrollController::class, 'bulkUpdateDevicePins'])->name('employees.device-pins');
        Route::get('/employees/{publicId}', [PayrollController::class, 'showEmployee'])->name('employees.show');
        Route::put('/employees/{publicId}', [PayrollController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{publicId}', [PayrollController::class, 'destroyEmployee'])->name('employees.destroy');
        Route::get('/employees/{publicId}/leave-balance', [PayrollController::class, 'leaveBalance'])->name('employees.leave-balance');
        Route::put('/employees/{publicId}/leave-balance', [PayrollController::class, 'adjustLeaveBalance'])->name('employees.leave-balance.adjust');

        Route::get('/payroll-runs', [PayrollController::class, 'payrollRuns'])->name('payroll-runs.index');
        Route::post('/payroll-runs', [PayrollController::class, 'storePayrollRun'])->name('payroll-runs.store');
        Route::get('/payroll-runs/{publicId}', [PayrollController::class, 'showPayrollRun'])->name('payroll-runs.show');
        Route::post('/payroll-runs/{publicId}/calculate', [PayrollController::class, 'calculatePayrollRun'])->name('payroll-runs.calculate');
        Route::post('/payroll-runs/{publicId}/post', [PayrollController::class, 'postPayrollRun'])->name('payroll-runs.post');
        Route::post('/payroll-runs/{publicId}/disburse', [PayrollController::class, 'disbursePayrollRun'])->name('payroll-runs.disburse');
        Route::get('/payroll-runs/{publicId}/payslip/{employeeId}', [PayrollController::class, 'payslip'])->name('payroll-runs.payslip');
        Route::get('/payroll-runs/{publicId}/bpjs-export', [PayrollController::class, 'exportBpjs'])->name('payroll-runs.bpjs-export');

        Route::prefix('leave-requests')->name('leave-requests.')->group(function (): void {
            Route::get('/', [LeaveController::class, 'index'])->name('index');
            Route::get('/preview-days', [LeaveController::class, 'previewDays'])->name('preview-days');
            Route::get('/{publicId}', [LeaveController::class, 'show'])->name('show');
            Route::post('/', [LeaveController::class, 'store'])->name('store');
            Route::post('/{publicId}/approve', [LeaveController::class, 'approve'])->name('approve');
            Route::post('/{publicId}/reject', [LeaveController::class, 'reject'])->name('reject');
            Route::post('/{publicId}/cancel', [LeaveController::class, 'cancel'])->name('cancel');
        });

        Route::prefix('attendance')->name('attendance.')->group(function (): void {
            Route::get('/settings', [AttendanceController::class, 'settings'])->name('settings');
            Route::get('/today', [AttendanceController::class, 'today'])->name('today');
            Route::post('/clock-in', [AttendanceController::class, 'clockIn'])->name('clock-in');
            Route::post('/clock-out', [AttendanceController::class, 'clockOut'])->name('clock-out');
            Route::post('/manual', [AttendanceController::class, 'createManual'])->name('manual');
            Route::put('/{publicId}/adjust', [AttendanceController::class, 'adjust'])->name('adjust');
            Route::get('/live', [AttendanceController::class, 'live'])->name('live');
            Route::get('/export', [AttendanceController::class, 'export'])->name('export');
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::get('/reports/monthly', [AttendanceController::class, 'monthlyReport'])->name('reports.monthly');
        });
    });

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/business-dashboard', [BusinessReportController::class, 'dashboard'])->name('business-dashboard');
    });
});