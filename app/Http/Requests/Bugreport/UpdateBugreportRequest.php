<?php

namespace App\Http\Requests\Bugreport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBugreportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps_to_reproduce' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'actual_result' => 'nullable|string',
            'severity' => 'required|in:critical,major,minor,trivial',
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:new,open,in_progress,resolved,closed,reopened',
            'environment' => 'nullable|string',
            'fixed_on' => 'nullable|array',
            'fixed_on.*' => 'in:develop,staging,production',
            'assigned_to' => 'nullable|exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'exists:project_features,id',
        ];
    }
}
