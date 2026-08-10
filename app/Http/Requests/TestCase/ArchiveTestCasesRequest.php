<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveTestCasesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_case_ids' => 'required|array|min:1',
            'test_case_ids.*' => 'integer|exists:test_cases,id',
            'archive_suite_id' => 'nullable|integer|exists:test_suites,id',
            'archive_suite_name' => 'required_without:archive_suite_id|nullable|string|max:255',
        ];
    }
}
