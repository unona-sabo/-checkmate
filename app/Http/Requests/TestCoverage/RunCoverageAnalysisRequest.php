<?php

namespace App\Http\Requests\TestCoverage;

use Illuminate\Foundation\Http\FormRequest;

class RunCoverageAnalysisRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'in:gemini,claude,openai'],
            'custom_instructions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
