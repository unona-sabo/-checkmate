<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class ImportChecklistCsvRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB max
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to import.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.max' => 'The file size must not exceed 5MB.',
        ];
    }
}
