<?php

namespace App\Http\Requests\TestData;

use Illuminate\Foundation\Http\FormRequest;

class UpsertPaymentMethodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:card,crypto,bank,paypal,other',
            'system' => 'nullable|string|max:255',
            'credentials' => 'nullable|array',
            'environment' => 'nullable|string|in:develop,staging,production',
            'is_valid' => 'boolean',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ];
    }
}
