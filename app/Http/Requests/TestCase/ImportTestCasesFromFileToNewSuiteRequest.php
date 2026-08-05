<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class ImportTestCasesFromFileToNewSuiteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'headers' => 'required|array|min:1',
            'headers.*' => 'required|string',
            'rows' => 'required|array|min:1',
            'rows.*' => 'required|array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'The file must contain at least one row of data.',
            'rows.min' => 'The file must contain at least one row of data.',
            'name.required' => 'Please enter a name for the test suite.',
        ];
    }
}
