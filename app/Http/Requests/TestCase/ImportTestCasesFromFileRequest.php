<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class ImportTestCasesFromFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_suite_id' => 'required|integer|exists:test_suites,id',
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
            'test_suite_id.required' => 'Please select a target test suite.',
            'test_suite_id.exists' => 'The selected test suite does not exist.',
        ];
    }
}
