<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTestCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'preconditions' => 'nullable|string',
            'steps' => 'nullable|array',
            'steps.*.action' => 'required|string',
            'steps.*.expected' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'severity' => 'required|in:trivial,minor,major,critical,blocker',
            'type' => 'required|in:functional,smoke,regression,integration,acceptance,performance,security,usability,other',
            'automation_status' => 'required|in:not_automated,to_be_automated,automated',
            'module' => 'nullable|array',
            'module.*' => 'string|in:UI,API,Backend,Database,Integration',
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'exists:project_features,id',
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
