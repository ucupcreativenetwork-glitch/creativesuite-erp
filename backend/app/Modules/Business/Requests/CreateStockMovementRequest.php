<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Enums\StockMovementType;
use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStockMovementRequest extends FormRequest
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
        return [
            'item_id' => ['required', 'integer', 'exists:cs_inv_items,id'],
            'warehouse_id' => ['required', 'integer', 'exists:cs_inv_warehouses,id'],
            'movement_type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}