<?php

namespace App\Http\Requests\DesignLink;

use Illuminate\Foundation\Http\FormRequest;

class UpsertDesignLinkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
        ];
    }
}
