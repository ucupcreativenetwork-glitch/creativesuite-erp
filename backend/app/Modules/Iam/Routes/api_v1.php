<?php

use App\Modules\Iam\Controllers\Api\V1\BranchController;
use App\Modules\Iam\Controllers\Api\V1\DepartmentController;
use App\Modules\Iam\Controllers\Api\V1\DepartmentRoleMappingController;
use App\Modules\Iam\Controllers\Api\V1\IamBootstrapController;
use App\Modules\Iam\Controllers\Api\V1\IamDashboardController;
use App\Modules\Iam\Controllers\Api\V1\IamFormOptionsController;
use App\Modules\Iam\Controllers\Api\V1\NotificationController;
use App\Modules\Iam\Controllers\Api\V1\PushDeviceController;
use App\Modules\Iam\Controllers\Api\V1\UserCreationRequestController;
use App\Modules\Iam\Controllers\Api\V1\UserRevokeController;
use App\Modules\Iam\Controllers\Api\V1\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'company.context'])->group(function (): void {
    Route::prefix('user-creation-requests')->name('user-creation-requests.')->group(function (): void {
        Route::get('/', [UserCreationRequestController::class, 'index'])->name('index');
        Route::post('/', [UserCreationRequestController::class, 'store'])->name('store');
        Route::get('/{publicId}', [UserCreationRequestController::class, 'show'])->name('show');
        Route::put('/{publicId}', [UserCreationRequestController::class, 'update'])->name('update');
        Route::post('/{publicId}/submit', [UserCreationRequestController::class, 'submit'])->name('submit');
        Route::post('/{publicId}/cancel', [UserCreationRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{publicId}/approve', [UserCreationRequestController::class, 'approve'])->name('approve');
        Route::post('/{publicId}/reject', [UserCreationRequestController::class, 'reject'])->name('reject');
        Route::post('/{publicId}/request-revision', [UserCreationRequestController::class, 'requestRevision'])->name('revision');
        Route::post('/{publicId}/override-approve', [UserCreationRequestController::class, 'overrideApprove'])->name('override');
    });

    Route::prefix('iam')->name('iam.')->group(function (): void {
        Route::get('/dashboard/head', [IamDashboardController::class, 'head'])->name('dashboard.head');
        Route::get('/dashboard/approver', [IamDashboardController::class, 'approver'])->name('dashboard.approver');
        Route::get('/dashboard/owner', [IamDashboardController::class, 'owner'])->name('dashboard.owner');
        Route::post('/bootstrap', [IamBootstrapController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/form-options', [IamFormOptionsController::class, 'show'])->name('form-options');
        Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
        Route::post('/workflows/seed-alternatives', [WorkflowController::class, 'seedAlternatives'])->name('workflows.seed');
        Route::put('/workflows/{publicId}/default', [WorkflowController::class, 'setDefault'])->name('workflows.default');
        Route::get('/department-role-mappings', [DepartmentRoleMappingController::class, 'index'])->name('department-role-mappings.index');
        Route::put('/departments/{publicId}/role-mappings', [DepartmentRoleMappingController::class, 'update'])->name('department-role-mappings.update');
        Route::post('/users/{publicId}/revoke', [UserRevokeController::class, 'revoke'])->name('users.revoke');
    });

    Route::prefix('branches')->name('branches.')->group(function (): void {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('/{branchId}', [BranchController::class, 'show'])->whereNumber('branchId')->name('show');
        Route::put('/{branchId}', [BranchController::class, 'update'])->whereNumber('branchId')->name('update');
    });

    Route::prefix('departments')->name('departments.')->group(function (): void {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::get('/my', [DepartmentController::class, 'myDepartment'])->name('my');
        Route::get('/{publicId}/allowed-roles', [DepartmentController::class, 'allowedRoles'])->name('allowed-roles');
    });

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/push/register', [PushDeviceController::class, 'register'])->name('push.register');
        Route::delete('/push/unregister', [PushDeviceController::class, 'unregister'])->name('push.unregister');
        Route::put('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
    });
});