<?php

namespace App\Http\Requests\TestSuite;

use Illuminate\Foundation\Http\FormRequest;

class CopySuitesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'project_id' => 'required|integer|exists:projects,id',
        ];
    }
}
