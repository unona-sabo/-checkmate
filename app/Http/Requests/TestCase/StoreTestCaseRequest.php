<?php

namespace App\Http\Requests\TestCase;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestCaseRequest extends FormRequest
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
            'tags' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip',
            'checklist_id' => 'nullable|integer|exists:checklists,id',
            'checklist_row_ids' => 'nullable|string',
            'checklist_link_column' => 'nullable|string|max:255',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'exists:project_features,id',
            'bugreport_id' => 'nullable|integer|exists:bugreports,id',
        ];
    }
}
