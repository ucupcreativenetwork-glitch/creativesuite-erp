<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;

class AttendanceExportService
{
    use ChecksPermissions;

    public function exportCsv(User $user, array $filters): array
    {
        $this->assertAnyPermission($user, ['hr.attendance.manage', 'hr.attendance.report']);

        $query = AttendanceRecord::query()
            ->where('company_id', $user->default_company_id)
            ->with('employee')
            ->orderBy('attendance_date')
            ->orderBy('clock_in_at');

        if (! empty($filters['from_date'])) {
            $query->whereDate('attendance_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('attendance_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_public_id'])) {
            $query->whereHas('employee', fn ($q) => $q->where('public_id', $filters['employee_public_id']));
        }

        $records = $query->limit(5000)->get();

        $rows = [[
            'Tanggal', 'NIK', 'Nama', 'Departemen', 'Masuk', 'Pulang', 'Status',
            'Telat (mnt)', 'Jam Kerja (mnt)', 'GPS Masuk', 'Akurasi GPS (m)', 'Selfie Masuk',
            'Catatan',
        ]];

        foreach ($records as $record) {
            $rows[] = [
                $record->attendance_date?->format('Y-m-d'),
                $record->employee?->employee_number ?? '',
                $record->employee?->full_name ?? '',
                $record->employee?->department ?? '',
                $record->clock_in_at?->format('H:i:s') ?? '',
                $record->clock_out_at?->format('H:i:s') ?? '',
                $record->status?->value ?? $record->status,
                (int) $record->late_minutes,
                (int) $record->work_minutes,
                $record->clock_in_latitude !== null
                    ? "{$record->clock_in_latitude},{$record->clock_in_longitude}"
                    : '',
                $record->clock_in_accuracy_m ?? '',
                $record->clock_in_photo_path ? 'Ya' : 'Tidak',
                $record->notes ?? '',
            ];
        }

        $csv = $this->toCsv($rows);
        $from = $filters['from_date'] ?? 'all';
        $to = $filters['to_date'] ?? 'all';

        return [
            'filename' => "absensi-{$from}-{$to}.csv",
            'content' => base64_encode($csv),
            'row_count' => max(0, count($rows) - 1),
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($v) => (string) $v, $row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    protected function assertAnyPermission(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        throw new ApiException('Akses ditolak.', 403, 'FORBIDDEN');
    }
}