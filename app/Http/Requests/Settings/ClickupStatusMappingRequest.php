<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ClickupStatusMappingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status_mapping' => ['required', 'array'],
            'status_mapping.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mapping = collect($this->input('status_mapping', []))
                ->filter(fn ($status) => filled($status))
                ->map(fn ($status) => strtolower($status));

            $duplicates = $mapping->duplicates();

            if ($duplicates->isNotEmpty()) {
                $validator->errors()->add(
                    'status_mapping',
                    'Each ClickUp status can only be mapped from one app status — "'.$duplicates->first().'" is used more than once.'
                );
            }
        });
    }
}
