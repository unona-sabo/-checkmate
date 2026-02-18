<?php

namespace App\Http\Requests\WorkspaceMember;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => 'required|string|in:admin,member,viewer',
        ];
    }
}
