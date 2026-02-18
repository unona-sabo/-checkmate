<?php

namespace App\Http\Requests\Release;

use Illuminate\Foundation\Http\FormRequest;

class StoreReleaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'planned_date' => ['nullable', 'date'],
        ];
    }
}
