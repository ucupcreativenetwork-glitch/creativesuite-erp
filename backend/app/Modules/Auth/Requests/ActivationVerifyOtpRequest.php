<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivationVerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            'otp_session_token' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ];
    }
}