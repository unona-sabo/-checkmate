<?php

namespace App\Http\Requests\TestData;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTestLinkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category' => 'nullable|string|max:255',
            'description' => 'required|string|max:255',
            'url' => 'required|string|url|max:2048',
            'comment' => 'nullable|string|max:2000',
        ];
    }
}
