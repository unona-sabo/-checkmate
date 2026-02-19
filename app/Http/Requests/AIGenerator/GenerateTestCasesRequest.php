<?php

namespace App\Http\Requests\AIGenerator;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTestCasesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'input_type' => ['required', 'string', 'in:text,file,image'],
            'text' => ['required_if:input_type,text', 'nullable', 'string', 'max:50000'],
            'file' => ['required_if:input_type,file', 'nullable', 'file', 'mimes:txt,md', 'max:2048'],
            'image' => ['required_if:input_type,image', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'provider' => ['nullable', 'string', 'in:gemini,claude'],
            'custom_prompt' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required_if' => 'Text content is required when using text input.',
            'file.required_if' => 'A file is required when using file input.',
            'image.required_if' => 'An image is required when using image input.',
            'text.max' => 'Text content must not exceed 50,000 characters.',
            'file.max' => 'File must not exceed 2MB.',
            'image.max' => 'Image must not exceed 10MB.',
        ];
    }
}
