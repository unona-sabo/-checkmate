<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistFromNotesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'notes' => 'required|array',
            'notes.*' => 'required|string',
        ];
    }
}
