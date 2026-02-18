<?php

namespace App\Http\Requests\Home;

use Illuminate\Foundation\Http\FormRequest;

class UpsertFeatureDescriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
