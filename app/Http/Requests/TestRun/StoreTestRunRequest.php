<?php

namespace App\Http\Requests\TestRun;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestRunRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'environment' => 'nullable|string|max:255',
            'milestone' => 'nullable|string|max:255',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'test_case_ids' => 'required|array|min:1',
            'test_case_ids.*' => 'exists:test_cases,id',
        ];
    }
}
