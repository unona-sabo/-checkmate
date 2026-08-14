<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class ImportChecklistCsvToNewChecklistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB max
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a name for the checklist.',
            'file.required' => 'Please select a file to import.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.max' => 'The file size must not exceed 5MB.',
        ];
    }
}
