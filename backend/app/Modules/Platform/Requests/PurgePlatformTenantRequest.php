<?php

namespace App\Modules\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurgePlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'max:120'],
        ];
    }
}