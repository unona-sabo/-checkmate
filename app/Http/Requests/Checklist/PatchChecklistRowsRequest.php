<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class PatchChecklistRowsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rows' => 'required|array',
            'rows.*.id' => 'required|integer',
            'rows.*.data' => 'required|array',
            'rows.*.order' => 'required|integer',
            'rows.*.row_type' => 'nullable|in:normal,section_header',
            'rows.*.background_color' => 'nullable|string|max:7',
            'rows.*.font_color' => 'nullable|string|max:7',
            'rows.*.font_weight' => 'nullable|in:normal,medium,semibold,bold',
            'rows.*.module' => 'nullable|array',
            'rows.*.module.*' => 'string|max:50',
        ];
    }
}
