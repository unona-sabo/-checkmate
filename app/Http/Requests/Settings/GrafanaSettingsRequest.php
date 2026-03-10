<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class GrafanaSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'api_token' => ['nullable', 'string'],
            'base_url' => ['required', 'url'],
            'datasource_id' => ['required', 'string'],
            'log_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
