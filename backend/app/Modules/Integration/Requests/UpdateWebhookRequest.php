<?php

namespace App\Modules\Integration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(config('integration.webhook_events', []))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}