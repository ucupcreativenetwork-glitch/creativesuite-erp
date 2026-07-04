<?php

namespace App\Modules\Business\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateDevicePinsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array', 'min:1', 'max:200'],
            'mappings.*.public_id' => ['required', 'string', 'uuid'],
            'mappings.*.device_pin' => ['nullable', 'string', 'max:40'],
        ];
    }
}