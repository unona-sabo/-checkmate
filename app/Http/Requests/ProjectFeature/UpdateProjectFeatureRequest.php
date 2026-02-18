<?php

namespace App\Http\Requests\ProjectFeature;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'module' => 'nullable|array',
            'module.*' => 'string|max:100',
            'priority' => 'required|in:critical,high,medium,low',
        ];
    }
}
