<?php

namespace App\Http\Requests\Documentation;

use Illuminate\Foundation\Http\FormRequest;

class ImportDocumentationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,csv,txt,json',
            'title' => 'nullable|string|max:255',
        ];
    }
}
