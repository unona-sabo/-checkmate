<?php

namespace App\Http\Requests\TestData;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTestUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'nullable|string|max:1000',
            'role' => 'nullable|string|max:100',
            'environment' => 'nullable|string|in:develop,staging,production',
            'description' => 'nullable|string|max:2000',
            'is_valid' => 'boolean',
            'additional_info' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ];
    }
}
