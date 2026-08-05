<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestCaseNoteAsNewSuiteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'suite_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.action' => 'required|string',
            'steps.*.expected' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'suite_name.required' => 'Please enter a name for the test suite.',
        ];
    }
}
