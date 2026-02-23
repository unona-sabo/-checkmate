<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistRowsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rows' => 'required|array',
            'rows.*.id' => 'nullable|integer',
            'rows.*.data' => 'required|array',
            'rows.*.order' => 'required|integer',
            'rows.*.row_type' => 'nullable|in:normal,section_header',
            'rows.*.background_color' => 'nullable|string|max:7',
            'rows.*.font_color' => 'nullable|string|max:7',
            'rows.*.font_weight' => 'nullable|in:normal,medium,semibold,bold',
            'rows.*.module' => 'nullable|array',
            'rows.*.module.*' => 'string|max:50',
            'columns_config' => 'nullable|array',
            'columns_config.*.key' => 'required|string',
            'columns_config.*.label' => 'required|string',
            'columns_config.*.type' => 'required|in:text,checkbox,select,date',
            'columns_config.*.width' => 'nullable|integer|min:50',
            'columns_config.*.options' => 'nullable|array',
            'columns_config.*.options.*.value' => 'required|string',
            'columns_config.*.options.*.label' => 'required|string',
            'columns_config.*.options.*.color' => 'nullable|string|max:7',
        ];
    }
}
