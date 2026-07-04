<?php

namespace App\Modules\Iam\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'is_head_office' => (bool) $this->is_head_office,
            'is_active' => (bool) $this->is_active,
            'attendance_geofence_enabled' => (bool) $this->attendance_geofence_enabled,
            'attendance_latitude' => $this->attendance_latitude !== null ? (float) $this->attendance_latitude : null,
            'attendance_longitude' => $this->attendance_longitude !== null ? (float) $this->attendance_longitude : null,
            'attendance_geofence_radius_m' => (int) ($this->attendance_geofence_radius_m ?? 150),
        ];
    }
}