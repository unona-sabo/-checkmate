<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class UnarchiveTestCasesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_case_ids' => 'required|array|min:1',
            'test_case_ids.*' => 'integer|exists:test_cases,id',
            'mode' => 'required|string|in:original,choose',
            'target_suite_id' => 'required_if:mode,choose|nullable|integer|exists:test_suites,id',
        ];
    }
}
