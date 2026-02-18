<?php

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutomationResultsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'results' => 'required|array',
        ];
    }
}
