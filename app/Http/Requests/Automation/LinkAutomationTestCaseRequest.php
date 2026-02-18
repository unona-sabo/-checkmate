<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class LinkAutomationTestCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_case_id' => 'required|exists:test_cases,id',
            'playwright_file' => 'required|string',
            'playwright_test_name' => 'required|string',
        ];
    }
}
