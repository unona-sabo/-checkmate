<?php

namespace App\Http\Requests\Documentation;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:documentations,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->file('attachments') ?? [] as $index => $file) {
            $name = $file->getClientOriginalName();
            $messages["attachments.{$index}.file"] = "The file \"{$name}\" must be a valid file.";
            $messages["attachments.{$index}.max"] = "The file \"{$name}\" must not be larger than 10MB.";
            $messages["attachments.{$index}.mimes"] = "The file \"{$name}\" must be of type: jpg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, csv, zip.";
        }

        return $messages;
    }
}
