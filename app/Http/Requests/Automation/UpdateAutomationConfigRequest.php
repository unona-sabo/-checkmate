<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'automation_tests_path' => 'required|string|max:500',
        ];
    }
}
