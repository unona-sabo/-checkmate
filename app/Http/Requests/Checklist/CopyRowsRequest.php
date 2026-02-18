<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class CopyRowsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rows' => 'required|array|min:1',
            'rows.*.data' => 'required|array',
            'rows.*.row_type' => 'nullable|in:normal,section_header',
            'rows.*.background_color' => 'nullable|string|max:7',
            'rows.*.font_color' => 'nullable|string|max:7',
            'rows.*.font_weight' => 'nullable|in:normal,medium,semibold,bold',
            'section_row_id' => 'nullable|integer|exists:checklist_rows,id',
            'source_columns_config' => 'nullable|array',
            'source_columns_config.*.key' => 'required|string',
            'source_columns_config.*.label' => 'required|string',
            'source_columns_config.*.type' => 'required|string',
        ];
    }
}
