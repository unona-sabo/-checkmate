<?php

namespace App\Http\Requests\TestRunCase;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTestRunCasesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_run_case_ids' => 'required|array|min:1',
            'test_run_case_ids.*' => 'exists:test_run_cases,id',
            'status' => 'nullable|in:untested,passed,failed,blocked,skipped,retest',
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }
}
