<?php

namespace App\Http\Requests\TestRunCase;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTestRunCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:untested,passed,failed,blocked,skipped,retest',
            'actual_result' => 'nullable|string',
            'time_spent' => 'nullable|integer|min:0',
            'clickup_link' => 'nullable|url|max:255',
            'qase_link' => 'nullable|url|max:255',
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }
}
