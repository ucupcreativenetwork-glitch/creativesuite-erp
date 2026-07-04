<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\BulkUpdateDevicePinsRequest;
use App\Modules\Business\Requests\CreateEmployeeRequest;
use App\Modules\Business\Requests\CreatePayrollRunRequest;
use App\Modules\Business\Requests\DisbursePayrollRequest;
use App\Modules\Business\Requests\UpdateEmployeeRequest;
use App\Modules\Business\Resources\EmployeeResource;
use App\Modules\Business\Resources\PayrollRunResource;
use App\Modules\Business\Services\LeaveService;
use App\Modules\Business\Services\PayrollService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $service,
        protected LeaveService $leaveService,
    ) {}

    public function employees(): JsonResponse
    {
        $employees = $this->service->listEmployees(auth('api')->user(), request()->only([
            'status', 'search', 'per_page',
        ]));

        return ApiResponse::success(EmployeeResource::collection($employees));
    }

    public function showEmployee(string $publicId): JsonResponse
    {
        $employee = $this->service->showEmployee(auth('api')->user(), $publicId);

        return ApiResponse::success(new EmployeeResource($employee));
    }

    public function storeEmployee(CreateEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->createEmployee(auth('api')->user(), $request->validated());

        return ApiResponse::success(new EmployeeResource($employee), 'Employee created.', 201);
    }

    public function updateEmployee(UpdateEmployeeRequest $request, string $publicId): JsonResponse
    {
        $employee = $this->service->updateEmployee(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new EmployeeResource($employee), 'Employee updated.');
    }

    public function destroyEmployee(string $publicId): JsonResponse
    {
        $this->service->deleteEmployee(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Employee deleted.');
    }

    public function bulkUpdateDevicePins(BulkUpdateDevicePinsRequest $request): JsonResponse
    {
        $result = $this->service->bulkUpdateDevicePins(
            auth('api')->user(),
            $request->validated('mappings'),
        );

        return ApiResponse::success($result, 'PIN mesin absensi diperbarui.');
    }

    public function payrollRuns(): JsonResponse
    {
        $runs = $this->service->listPayrollRuns(auth('api')->user(), request()->only([
            'status', 'per_page',
        ]));

        return ApiResponse::success(PayrollRunResource::collection($runs));
    }

    public function showPayrollRun(string $publicId): JsonResponse
    {
        $run = $this->service->showPayrollRun(auth('api')->user(), $publicId);

        return ApiResponse::success(new PayrollRunResource($run));
    }

    public function storePayrollRun(CreatePayrollRunRequest $request): JsonResponse
    {
        $run = $this->service->createPayrollRun(auth('api')->user(), $request->validated());

        return ApiResponse::success(new PayrollRunResource($run), 'Payroll run created.', 201);
    }

    public function calculatePayrollRun(string $publicId): JsonResponse
    {
        $run = $this->service->calculate(auth('api')->user(), $publicId);

        return ApiResponse::success(new PayrollRunResource($run), 'Payroll calculated.');
    }

    public function postPayrollRun(string $publicId): JsonResponse
    {
        $run = $this->service->post(auth('api')->user(), $publicId);

        return ApiResponse::success(new PayrollRunResource($run), 'Payroll posted.');
    }

    public function disbursePayrollRun(DisbursePayrollRequest $request, string $publicId): JsonResponse
    {
        $run = $this->service->disbursePayroll(
            auth('api')->user(),
            $publicId,
            (int) $request->validated('bank_account_id'),
        );

        return ApiResponse::success(new PayrollRunResource($run), 'Payroll disbursed.');
    }

    public function payslip(string $publicId, int $employeeId): JsonResponse
    {
        $slip = $this->service->payslip(auth('api')->user(), $publicId, $employeeId);

        return ApiResponse::success($slip);
    }

    public function exportBpjs(string $publicId): JsonResponse
    {
        $export = $this->service->exportBpjs(auth('api')->user(), $publicId);

        return ApiResponse::success($export);
    }

    public function leaveBalance(string $publicId, Request $request): JsonResponse
    {
        $balance = $this->leaveService->leaveBalanceForEmployee(
            auth('api')->user(),
            $publicId,
            $request->integer('year') ?: null,
        );

        return ApiResponse::success($balance);
    }

    public function adjustLeaveBalance(string $publicId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'days' => ['required', 'numeric'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $balance = $this->leaveService->adjustLeaveBalance(
            auth('api')->user(),
            $publicId,
            (int) $validated['year'],
            (float) $validated['days'],
            $validated['notes'] ?? null,
        );

        return ApiResponse::success($balance, 'Saldo cuti disesuaikan.');
    }
}