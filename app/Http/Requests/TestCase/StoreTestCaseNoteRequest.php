<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestCaseNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'content' => 'nullable|string',
        ];
    }
}
