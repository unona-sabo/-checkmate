<?php

namespace App\Http\Requests\TestRun;

use Illuminate\Foundation\Http\FormRequest;

class AddCasesRequest extends FormRequest
{
    public function rules(): array
    {
        $testRun = $this->route('testRun');

        if ($testRun && $testRun->source === 'checklist') {
            return [
                'titles' => 'required|array|min:1',
                'titles.*' => 'required|string|max:1000',
                'expected_results' => 'nullable|array',
                'expected_results.*' => 'nullable|string',
            ];
        }

        return [
            'test_case_ids' => 'required|array|min:1',
            'test_case_ids.*' => 'required|integer|exists:test_cases,id',
        ];
    }
}
