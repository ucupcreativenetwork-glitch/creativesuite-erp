<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Requests\Concerns\ResolvesCompanyIdentifier;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }
}