<?php

namespace App\Http\Requests\TestSuite;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTestSuitesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'suites' => 'required|array',
            'suites.*.id' => 'required|exists:test_suites,id',
            'suites.*.order' => 'required|integer',
            'suites.*.parent_id' => 'nullable|exists:test_suites,id',
        ];
    }
}
