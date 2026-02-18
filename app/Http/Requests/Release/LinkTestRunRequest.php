<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class LinkTestRunRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'test_run_id' => ['required', 'exists:test_runs,id'],
        ];
    }
}
