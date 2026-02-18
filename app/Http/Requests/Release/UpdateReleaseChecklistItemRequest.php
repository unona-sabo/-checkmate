<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReleaseChecklistItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:20'],
            'priority' => ['sometimes', 'string', 'max:20'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'is_blocker' => ['sometimes', 'boolean'],
        ];
    }
}
