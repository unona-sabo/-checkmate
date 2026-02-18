<?php

namespace App\Http\Requests\Documentation;

use Illuminate\Foundation\Http\FormRequest;

class ReorderDocumentationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:documentations,id',
            'items.*.order' => 'required|integer|min:0',
        ];
    }
}
