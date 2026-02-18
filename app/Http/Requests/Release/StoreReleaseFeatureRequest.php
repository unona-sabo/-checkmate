<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class StoreReleaseFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'feature_id' => ['nullable', 'exists:project_features,id'],
            'feature_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:30'],
        ];
    }
}
