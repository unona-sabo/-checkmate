<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTestCasesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cases' => 'required|array',
            'cases.*.id' => 'required|exists:test_cases,id',
            'cases.*.order' => 'required|integer',
            'cases.*.test_suite_id' => 'nullable|exists:test_suites,id',
        ];
    }
}
