<?php

namespace App\Http\Requests\AIGenerator;

use Illuminate\Foundation\Http\FormRequest;

class ParseTestCasesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:50000'],
            'provider' => ['nullable', 'string', 'in:gemini,claude,openai'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Paste the test case text you want to import.',
            'text.max' => 'Text content must not exceed 50,000 characters.',
        ];
    }
}
