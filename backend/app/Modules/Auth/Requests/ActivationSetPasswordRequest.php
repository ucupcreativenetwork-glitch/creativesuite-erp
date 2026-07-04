<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class ActivationSetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ];
    }
}