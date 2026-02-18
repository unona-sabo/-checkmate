<?php

namespace App\Http\Requests\Checklist;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'content' => 'nullable|string',
        ];
    }
}
