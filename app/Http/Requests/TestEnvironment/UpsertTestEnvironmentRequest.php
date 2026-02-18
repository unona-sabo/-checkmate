<?php

namespace App\Http\Requests\TestEnvironment;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTestEnvironmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'base_url' => 'nullable|string|max:500',
            'variables' => 'nullable|array',
            'workers' => 'integer|min:1|max:32',
            'retries' => 'integer|min:0|max:10',
            'browser' => 'string|in:chromium,firefox,webkit',
            'headed' => 'boolean',
            'timeout' => 'integer|min:1000|max:300000',
            'description' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
        ];
    }
}
