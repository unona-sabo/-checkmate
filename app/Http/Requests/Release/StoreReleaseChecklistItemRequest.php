<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class StoreReleaseChecklistItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'string', 'max:20'],
            'is_blocker' => ['sometimes', 'boolean'],
        ];
    }
}
