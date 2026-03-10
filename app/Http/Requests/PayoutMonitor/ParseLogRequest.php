<?php

namespace App\Http\Requests\PayoutMonitor;

use Illuminate\Foundation\Http\FormRequest;

class ParseLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'raw_log' => ['required', 'string', 'max:500000'],
        ];
    }
}
