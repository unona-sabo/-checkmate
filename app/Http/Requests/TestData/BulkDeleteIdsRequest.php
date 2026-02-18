<?php

namespace App\Http\Requests\TestData;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteIdsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }
}
