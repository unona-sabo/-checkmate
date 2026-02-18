<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class UpsertNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'documentation_id' => 'nullable|exists:documentations,id',
            'is_draft' => 'boolean',
        ];
    }
}
