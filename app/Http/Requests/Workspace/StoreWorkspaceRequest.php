<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\x20-\x7E]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Workspace name may only contain Latin letters, numbers, and standard punctuation, since it is used to build the workspace URL.',
        ];
    }
}
