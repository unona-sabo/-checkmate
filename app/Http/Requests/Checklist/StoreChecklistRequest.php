<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'columns_config' => 'nullable|array',
            'columns_config.*.key' => 'required|string',
            'columns_config.*.label' => 'required|string',
            'columns_config.*.type' => 'required|in:text,checkbox,select,date',
            'columns_config.*.width' => 'nullable|integer|min:50',
            'columns_config.*.options' => 'nullable|array',
            'columns_config.*.options.*.value' => 'required|string',
            'columns_config.*.options.*.label' => 'required|string',
            'columns_config.*.options.*.color' => 'nullable|string|max:7',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'exists:project_features,id',
        ];
    }
}
