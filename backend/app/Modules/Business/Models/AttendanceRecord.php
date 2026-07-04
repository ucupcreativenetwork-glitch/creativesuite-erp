<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_hr_attendance_records';

    protected $fillable = [
        'tenant_id', 'company_id', 'public_id', 'employee_id', 'attendance_date',
        'clock_in_at', 'clock_in_latitude', 'clock_in_longitude', 'clock_in_accuracy_m', 'clock_in_photo_path',
        'clock_out_at', 'clock_out_latitude', 'clock_out_longitude', 'clock_out_accuracy_m', 'clock_out_photo_path',
        'status', 'work_minutes', 'late_minutes',
        'notes', 'source', 'external_ref', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_in_latitude' => 'decimal:7',
            'clock_in_longitude' => 'decimal:7',
            'clock_in_accuracy_m' => 'decimal:2',
            'clock_out_at' => 'datetime',
            'clock_out_latitude' => 'decimal:7',
            'clock_out_longitude' => 'decimal:7',
            'clock_out_accuracy_m' => 'decimal:2',
            'status' => AttendanceStatus::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}