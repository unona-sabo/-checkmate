<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class SwitchWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workspace_id' => 'required|exists:workspaces,id',
        ];
    }
}
