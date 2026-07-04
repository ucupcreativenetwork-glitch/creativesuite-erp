<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'attendance_date' => $this->attendance_date?->format('Y-m-d'),
            'clock_in_at' => $this->clock_in_at?->toIso8601String(),
            'clock_in_latitude' => $this->clock_in_latitude !== null ? (float) $this->clock_in_latitude : null,
            'clock_in_longitude' => $this->clock_in_longitude !== null ? (float) $this->clock_in_longitude : null,
            'clock_in_accuracy_m' => $this->clock_in_accuracy_m !== null ? (float) $this->clock_in_accuracy_m : null,
            'clock_in_photo_url' => $this->photoUrl($this->clock_in_photo_path),
            'clock_out_at' => $this->clock_out_at?->toIso8601String(),
            'clock_out_latitude' => $this->clock_out_latitude !== null ? (float) $this->clock_out_longitude : null,
            'clock_out_longitude' => $this->clock_out_longitude !== null ? (float) $this->clock_out_longitude : null,
            'clock_out_accuracy_m' => $this->clock_out_accuracy_m !== null ? (float) $this->clock_out_accuracy_m : null,
            'clock_out_photo_url' => $this->photoUrl($this->clock_out_photo_path),
            'status' => $this->status?->value ?? $this->status,
            'work_minutes' => (int) $this->work_minutes,
            'work_hours' => round($this->work_minutes / 60, 1),
            'late_minutes' => (int) $this->late_minutes,
            'notes' => $this->notes,
            'employee' => $this->whenLoaded('employee', fn () => [
                'public_id' => $this->employee->public_id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->department,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}