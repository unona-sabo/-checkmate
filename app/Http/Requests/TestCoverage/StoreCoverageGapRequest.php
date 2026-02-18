<?php

namespace App\Http\Requests\TestCoverage;

use Illuminate\Foundation\Http\FormRequest;

class StoreCoverageGapRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'feature' => 'required|string',
            'description' => 'required|string',
            'module' => 'nullable|string',
            'category' => 'nullable|string',
            'priority' => 'required|string',
        ];
    }
}
