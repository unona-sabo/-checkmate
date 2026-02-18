<?php

namespace App\Http\Requests\Documentation;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image' => 'required|image|max:10240',
        ];
    }
}
