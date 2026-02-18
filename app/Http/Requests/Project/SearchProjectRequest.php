<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class SearchProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => 'required|string|min:2|max:100',
        ];
    }
}
