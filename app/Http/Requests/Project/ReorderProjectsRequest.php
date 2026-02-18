<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProjectsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'projects' => 'required|array',
            'projects.*.id' => 'required|exists:projects,id',
            'projects.*.order' => 'required|integer',
        ];
    }
}
