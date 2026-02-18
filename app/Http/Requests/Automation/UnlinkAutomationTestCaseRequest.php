<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class UnlinkAutomationTestCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_case_id' => 'required|exists:test_cases,id',
        ];
    }
}
