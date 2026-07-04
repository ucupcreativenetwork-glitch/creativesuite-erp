<?php

namespace App\Modules\Iam\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'attendance_geofence_enabled' => ['sometimes', 'boolean'],
            'attendance_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'attendance_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_geofence_radius_m' => ['sometimes', 'integer', 'min:50', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $enabled = $this->boolean('attendance_geofence_enabled');
            if (! $enabled) {
                return;
            }

            $lat = $this->input('attendance_latitude');
            $lng = $this->input('attendance_longitude');

            if ($lat === null || $lng === null) {
                $validator->errors()->add(
                    'attendance_latitude',
                    'Koordinat latitude dan longitude wajib jika geofence aktif.',
                );
            }
        });
    }
}