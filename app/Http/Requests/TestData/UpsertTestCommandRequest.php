<?php

namespace App\Http\Requests\TestData;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTestCommandRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category' => 'nullable|string|max:255',
            'description' => 'required|string|max:255',
            'command' => 'required|string|max:5000',
            'comment' => 'nullable|string|max:2000',
        ];
    }
}
