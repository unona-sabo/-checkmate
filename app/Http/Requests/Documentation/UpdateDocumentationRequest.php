<?php

namespace App\Http\Requests\Documentation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:documentations,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
        ];
    }
}
