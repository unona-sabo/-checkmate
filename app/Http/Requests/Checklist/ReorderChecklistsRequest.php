<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class ReorderChecklistsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:checklists,id',
            'items.*.order' => 'required|integer|min:0',
            'items.*.category' => 'nullable|string|max:255',
        ];
    }
}
