<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Requests\Concerns\ResolvesCompanyIdentifier;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    use ResolvesCompanyIdentifier;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->companyIdentifierRules(),
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}