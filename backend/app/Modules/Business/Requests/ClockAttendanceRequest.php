<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Requests\Concerns\SanitizesInput;
use App\Modules\Business\Services\AttendanceCaptureValidator;
use App\Modules\Business\Services\HrSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class ClockAttendanceRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings(['notes']);
    }

    public function rules(): array
    {
        $enforce = app(AttendanceCaptureValidator::class)->shouldEnforce($this->isMobileClient());
        $user = auth('api')->user();
        $capture = $user
            ? app(HrSettingsService::class)->attendanceCaptureFor($user)
            : config('hr.attendance_capture', []);
        $requireGps = $enforce && (bool) ($capture['require_gps'] ?? true);
        $requireSelfie = $enforce && (bool) ($capture['require_selfie'] ?? true);

        return [
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => array_filter([
                $requireGps ? 'required' : 'nullable',
                'numeric',
                'between:-90,90',
            ]),
            'longitude' => array_filter([
                $requireGps ? 'required' : 'nullable',
                'numeric',
                'between:-180,180',
            ]),
            'accuracy_m' => array_filter([
                $requireGps ? 'required' : 'nullable',
                'numeric',
                'min:0',
                'max:5000',
            ]),
            'photo' => array_filter([
                $requireSelfie ? 'required' : 'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ]),
        ];
    }

    public function isMobileClient(): bool
    {
        return app(AttendanceCaptureValidator::class)->isMobileClient(
            $this->header('X-Client-App'),
        );
    }
}