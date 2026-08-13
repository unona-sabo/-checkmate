<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertNoteRequest extends FormRequest
{
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'documentation_id' => [
                'nullable',
                Rule::exists('documentations', 'id')->where('project_id', $project?->id),
            ],
            'is_draft' => 'boolean',
        ];
    }
}
