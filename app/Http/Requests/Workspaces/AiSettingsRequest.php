<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class AiSettingsRequest extends FormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gemini_api_key' => ['nullable', 'string'],
            'gemini_model' => ['nullable', 'string', 'max:255'],
            'anthropic_api_key' => ['nullable', 'string'],
            'openai_api_key' => ['nullable', 'string'],
            'openai_model' => ['nullable', 'string', 'max:255'],
            'default_provider' => ['required', 'in:gemini,claude,openai'],
        ];
    }
}
