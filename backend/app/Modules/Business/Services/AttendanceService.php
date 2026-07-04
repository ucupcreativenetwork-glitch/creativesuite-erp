<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Core\Models\User;
use App\Modules\Business\Services\EmployeeLinkService;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use App\Support\Tenant\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceService
{
    use ChecksPermissions;

    public function __construct(
        protected AttendanceCaptureValidator $captureValidator,
        protected AttendancePhotoStorage $photoStorage,
        protected HrNotificationService $hrNotifications,
    ) {}

    public function clockIn(User $user, array $data = [], $photo = null, bool $isMobileClient = false): AttendanceRecord
    {
        $this->assertPermission($user, 'hr.attendance.clock');
        $enforce = $this->captureValidator->shouldEnforce($isMobileClient);
        $this->captureValidator->validateGps(
            $user,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
            $enforce,
        );
        $this->captureValidator->validatePhoto($user, $photo, $enforce);

        $employee = $this->resolveEmployee($user);
        $now = $this->tenantNow($user);
        $today = $now->toDateString();
        $notes = $data['notes'] ?? null;
        $captureFields = $this->buildClockInCaptureFields($user, $data, $photo);

        return DB::transaction(function () use ($user, $employee, $today, $now, $notes, $captureFields) {
            $record = $this->findTodayRecord($user->tenant_id, $employee->company_id, $employee->id, $today, lock: true);

            if ($record?->clock_in_at) {
                throw new ApiException('Anda sudah absen masuk hari ini.', 422, 'ALREADY_CLOCKED_IN');
            }

            if ($record?->status === AttendanceStatus::Leave) {
                throw new ApiException('Hari ini tercatat cuti/izin. Tidak bisa absen masuk.', 422, 'ON_LEAVE');
            }

            $lateMinutes = $this->calculateLateMinutes($user, $now);
            $payload = [
                'clock_in_at' => $now,
                'status' => $lateMinutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present,
                'late_minutes' => $lateMinutes,
                'notes' => $notes,
                ...$captureFields,
            ];

            if ($record) {
                $record->update($payload);
                $fresh = $record->fresh(['employee']);
                if ($lateMinutes > 0) {
                    $this->hrNotifications->notifyLateClockIn($fresh);
                }

                return $fresh;
            }

            try {
                $created = AttendanceRecord::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'company_id' => $employee->company_id,
                    'public_id' => (string) Str::uuid(),
                    'employee_id' => $employee->id,
                    'attendance_date' => $today,
                    'created_by' => $user->id,
                    ...$payload,
                ])->fresh(['employee']);
                if ($lateMinutes > 0) {
                    $this->hrNotifications->notifyLateClockIn($created);
                }

                return $created;
            } catch (UniqueConstraintViolationException) {
                $record = $this->findTodayRecord($user->tenant_id, $employee->company_id, $employee->id, $today, lock: true);

                if ($record?->clock_in_at) {
                    throw new ApiException('Anda sudah absen masuk hari ini.', 422, 'ALREADY_CLOCKED_IN');
                }

                if ($record?->status === AttendanceStatus::Leave) {
                    throw new ApiException('Hari ini tercatat cuti/izin. Tidak bisa absen masuk.', 422, 'ON_LEAVE');
                }

                if (! $record) {
                    throw new ApiException('Gagal mencatat absensi. Silakan coba lagi.', 500, 'ATTENDANCE_CONFLICT');
                }

                $record->update($payload);
                $fresh = $record->fresh(['employee']);
                if ($lateMinutes > 0) {
                    $this->hrNotifications->notifyLateClockIn($fresh);
                }

                return $fresh;
            }
        });
    }

    public function clockOut(User $user, array $data = [], $photo = null, bool $isMobileClient = false): AttendanceRecord
    {
        $this->assertPermission($user, 'hr.attendance.clock');
        $enforce = $this->captureValidator->shouldEnforce($isMobileClient);
        $this->captureValidator->validateGps(
            $user,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
            $enforce,
        );
        $this->captureValidator->validatePhoto($user, $photo, $enforce);

        $employee = $this->resolveEmployee($user);
        $now = $this->tenantNow($user);
        $today = $now->toDateString();
        $notes = $data['notes'] ?? null;
        $captureFields = $this->buildClockOutCaptureFields($user, $data, $photo);

        return DB::transaction(function () use ($employee, $today, $now, $notes, $captureFields) {
            $record = $this->findTodayRecord(
                $employee->tenant_id,
                $employee->company_id,
                $employee->id,
                $today,
                lock: true,
            );

            if (! $record?->clock_in_at) {
                throw new ApiException('Anda belum absen masuk hari ini.', 422, 'NOT_CLOCKED_IN');
            }

            if ($record->clock_out_at) {
                throw new ApiException('Anda sudah absen pulang hari ini.', 422, 'ALREADY_CLOCKED_OUT');
            }

            $workMinutes = max(0, $record->clock_in_at->diffInMinutes($now));

            $record->update([
                'clock_out_at' => $now,
                'work_minutes' => $workMinutes,
                'notes' => $notes ? trim(($record->notes ? $record->notes."\n" : '').$notes) : $record->notes,
                ...$captureFields,
            ]);

            return $record->fresh(['employee']);
        });
    }

    public function today(User $user): ?AttendanceRecord
    {
        $this->assertPermission($user, 'hr.attendance.clock');

        try {
            $employee = $this->resolveEmployee($user);
        } catch (ApiException) {
            return null;
        }

        return $this->findTodayRecord(
            $employee->tenant_id,
            $employee->company_id,
            $employee->id,
            $this->tenantNow($user)->toDateString(),
        )?->load('employee');
    }

    protected function findTodayRecord(
        int $tenantId,
        int $companyId,
        int $employeeId,
        string $date,
        bool $lock = false,
    ): ?AttendanceRecord {
        $query = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'hr.attendance.read');

        $query = AttendanceRecord::query()
            ->with('employee')
            ->orderByDesc('attendance_date')
            ->orderByDesc('clock_in_at');

        if (! empty($filters['from_date'])) {
            $query->where('attendance_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('attendance_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_public_id'])) {
            $query->whereHas('employee', fn ($q) => $q->where('public_id', $filters['employee_public_id']));
        } elseif (! $user->hasPermission('hr.attendance.manage')) {
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function adjust(User $user, string $publicId, array $data): AttendanceRecord
    {
        $this->assertPermission($user, 'hr.attendance.manage');

        $record = AttendanceRecord::query()
            ->with('employee')
            ->where('public_id', $publicId)
            ->where('company_id', $user->default_company_id)
            ->firstOrFail();

        $payload = [];

        if (array_key_exists('clock_in_at', $data)) {
            $payload['clock_in_at'] = $data['clock_in_at']
                ? Carbon::parse($data['clock_in_at'])
                : null;
        }

        if (array_key_exists('clock_out_at', $data)) {
            $payload['clock_out_at'] = $data['clock_out_at']
                ? Carbon::parse($data['clock_out_at'])
                : null;
        }

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $payload['status'] = $data['status'];
        }

        if (array_key_exists('notes', $data)) {
            $payload['notes'] = $data['notes'];
        }

        if (! empty($data['clear_capture'])) {
            $payload = array_merge($payload, [
                'clock_in_latitude' => null,
                'clock_in_longitude' => null,
                'clock_in_accuracy_m' => null,
                'clock_in_photo_path' => null,
                'clock_out_latitude' => null,
                'clock_out_longitude' => null,
                'clock_out_accuracy_m' => null,
                'clock_out_photo_path' => null,
            ]);
        }

        $clockIn = $payload['clock_in_at'] ?? $record->clock_in_at;
        $clockOut = $payload['clock_out_at'] ?? $record->clock_out_at;

        if ($clockIn && $clockOut) {
            $payload['work_minutes'] = max(0, $clockIn->diffInMinutes($clockOut));
        } elseif (array_key_exists('clock_out_at', $data) && $data['clock_out_at'] === null) {
            $payload['work_minutes'] = 0;
        }

        if (isset($payload['clock_in_at']) && $payload['clock_in_at']) {
            $payload['late_minutes'] = $this->calculateLateMinutes($user, $payload['clock_in_at']);
            if (! isset($payload['status'])) {
                $payload['status'] = $payload['late_minutes'] > 0
                    ? AttendanceStatus::Late
                    : AttendanceStatus::Present;
            }
        }

        $record->update($payload);

        return $record->fresh(['employee']);
    }

    public function createManual(User $user, array $data): AttendanceRecord
    {
        $this->assertPermission($user, 'hr.attendance.manage');

        $employee = Employee::query()
            ->where('public_id', $data['employee_public_id'])
            ->where('company_id', $user->default_company_id)
            ->where('status', EmployeeStatus::Active)
            ->firstOrFail();

        $date = $data['attendance_date'];
        $existing = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing) {
            throw new ApiException(
                'Absensi untuk tanggal ini sudah ada. Gunakan koreksi pada record yang ada.',
                422,
                'ATTENDANCE_EXISTS',
            );
        }

        $clockIn = isset($data['clock_in_at']) ? Carbon::parse($data['clock_in_at']) : null;
        $clockOut = isset($data['clock_out_at']) ? Carbon::parse($data['clock_out_at']) : null;
        $status = AttendanceStatus::from($data['status']);
        $lateMinutes = $clockIn ? $this->calculateLateMinutes($user, $clockIn) : 0;
        $workMinutes = ($clockIn && $clockOut) ? max(0, $clockIn->diffInMinutes($clockOut)) : 0;

        if ($clockIn && ! isset($data['status'])) {
            $status = $lateMinutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present;
        }

        return AttendanceRecord::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $employee->company_id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'work_minutes' => $workMinutes,
            'notes' => $data['notes'] ?? null,
            'source' => 'manual',
            'created_by' => $user->id,
        ])->fresh(['employee']);
    }

    public function monthlyReport(User $user, int $year, int $month): array
    {
        $this->assertPermission($user, 'hr.attendance.report');

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rows = AttendanceRecord::query()
            ->select([
                'employee_id',
                DB::raw("SUM(CASE WHEN status IN ('PRESENT','LATE') THEN 1 ELSE 0 END) as days_present"),
                DB::raw("SUM(CASE WHEN status = 'LATE' THEN 1 ELSE 0 END) as days_late"),
                DB::raw("SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END) as days_absent"),
                DB::raw("SUM(CASE WHEN status = 'LEAVE' THEN 1 ELSE 0 END) as days_leave"),
                DB::raw('SUM(work_minutes) as total_work_minutes'),
                DB::raw('SUM(late_minutes) as total_late_minutes'),
            ])
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('employee_id')
            ->get();

        $employees = Employee::query()
            ->whereIn('id', $rows->pluck('employee_id'))
            ->get()
            ->keyBy('id');

        $summary = $rows->map(function ($row) use ($employees) {
            $employee = $employees->get($row->employee_id);

            return [
                'employee' => $employee ? [
                    'public_id' => $employee->public_id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department,
                ] : null,
                'days_present' => (int) $row->days_present,
                'days_late' => (int) $row->days_late,
                'days_absent' => (int) $row->days_absent,
                'days_leave' => (int) $row->days_leave,
                'total_work_hours' => round(((int) $row->total_work_minutes) / 60, 1),
                'total_late_minutes' => (int) $row->total_late_minutes,
            ];
        })->values()->all();

        return [
            'period_year' => $year,
            'period_month' => $month,
            'period_label' => $start->translatedFormat('F Y'),
            'employees' => $summary,
        ];
    }

    protected function resolveEmployee(User $user): Employee
    {
        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('status', EmployeeStatus::Active)
            ->first();

        if (! $employee) {
            $employee = app(EmployeeLinkService::class)->ensureForUser($user);
        }

        if (! $employee || $employee->status !== EmployeeStatus::Active) {
            throw new ApiException(
                'Data karyawan belum tersedia. Pastikan akun aktif dan hubungi HRD.',
                422,
                'EMPLOYEE_NOT_LINKED',
            );
        }

        return $employee;
    }

    protected function buildClockInCaptureFields(User $user, array $data, $photo): array
    {
        $fields = [];

        if (isset($data['latitude'], $data['longitude'], $data['accuracy_m'])) {
            $fields['clock_in_latitude'] = (float) $data['latitude'];
            $fields['clock_in_longitude'] = (float) $data['longitude'];
            $fields['clock_in_accuracy_m'] = (float) $data['accuracy_m'];
        }

        if ($photo) {
            $fields['clock_in_photo_path'] = $this->photoStorage->storeClockPhoto($user, $photo, 'in');
        }

        return $fields;
    }

    protected function buildClockOutCaptureFields(User $user, array $data, $photo): array
    {
        $fields = [];

        if (isset($data['latitude'], $data['longitude'], $data['accuracy_m'])) {
            $fields['clock_out_latitude'] = (float) $data['latitude'];
            $fields['clock_out_longitude'] = (float) $data['longitude'];
            $fields['clock_out_accuracy_m'] = (float) $data['accuracy_m'];
        }

        if ($photo) {
            $fields['clock_out_photo_path'] = $this->photoStorage->storeClockPhoto($user, $photo, 'out');
        }

        return $fields;
    }

    protected function tenantNow(User $user): Carbon
    {
        $tenant = app(TenantManager::class)->get();

        if (! $tenant && $user->relationLoaded('tenant')) {
            $tenant = $user->tenant;
        }

        if (! $tenant) {
            $tenant = $user->tenant()->first();
        }

        $timezone = $tenant?->timezone ?: config('app.timezone', 'UTC');

        return Carbon::now($timezone);
    }

    protected function calculateLateMinutes(User $user, Carbon $clockIn): int
    {
        $policy = app(HrSettingsService::class)->resolve($user);
        $workStart = $policy['work_start'];
        $grace = (int) $policy['late_grace_minutes'];

        [$hour, $minute] = array_map('intval', explode(':', $workStart));
        $scheduled = $clockIn->copy()->setTime($hour, $minute, 0)->addMinutes($grace);

        if ($clockIn->lte($scheduled)) {
            return 0;
        }

        return (int) $scheduled->diffInMinutes($clockIn);
    }
}