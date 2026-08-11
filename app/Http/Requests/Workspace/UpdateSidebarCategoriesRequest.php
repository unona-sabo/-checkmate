<?php

namespace App\Http\Requests\Workspace;

use App\Enums\SidebarCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSidebarCategoriesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'hidden_categories' => ['array'],
            'hidden_categories.*' => [Rule::in(SidebarCategory::values())],
        ];
    }
}
