<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveEfakturRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nomor_faktur' => ['required', 'string', 'max:20'],
            'djp_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}