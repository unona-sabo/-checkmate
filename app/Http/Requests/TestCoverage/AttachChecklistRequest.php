<?php

namespace App\Http\Requests\TestCoverage;

use Illuminate\Foundation\Http\FormRequest;

class AttachChecklistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'checklist_id' => 'required|exists:checklists,id',
        ];
    }
}
