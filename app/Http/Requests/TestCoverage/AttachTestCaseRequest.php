<?php

namespace App\Http\Requests\TestCoverage;

use Illuminate\Foundation\Http\FormRequest;

class AttachTestCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_case_id' => 'required|exists:test_cases,id',
        ];
    }
}
