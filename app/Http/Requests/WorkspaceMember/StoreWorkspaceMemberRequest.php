<?php

namespace App\Http\Requests\WorkspaceMember;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string|in:admin,member,viewer',
        ];
    }
}
