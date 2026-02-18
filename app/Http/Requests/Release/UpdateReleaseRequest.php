<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReleaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'planned_date' => ['nullable', 'date'],
            'actual_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'max:30'],
            'health' => ['sometimes', 'string', 'max:10'],
            'decision' => ['sometimes', 'string', 'max:30'],
            'decision_notes' => ['nullable', 'string'],
        ];
    }
}
