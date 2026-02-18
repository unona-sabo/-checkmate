<?php

namespace App\Http\Requests\TestRunTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTestRunTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'environment_id' => 'nullable|exists:test_environments,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'tag_mode' => 'string|in:or,and',
            'file_pattern' => 'nullable|string|max:500',
            'options' => 'nullable|array',
        ];
    }
}
