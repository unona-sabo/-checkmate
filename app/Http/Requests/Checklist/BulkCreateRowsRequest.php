<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class BulkCreateRowsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => 'required|array',
            'notes.*' => 'required|string',
            'column_key' => 'required|string',
            'section_row_id' => 'nullable|integer|exists:checklist_rows,id',
        ];
    }
}
