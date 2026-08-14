<?php

namespace App\Http\Requests\Documentation;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentationNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ];
    }
}
