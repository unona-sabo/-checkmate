<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ClickupStatusMappingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status_mapping' => ['required', 'array'],
            'status_mapping.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mapping = collect($this->input('status_mapping', []))
                ->filter(fn ($status) => filled($status))
                ->map(fn ($status) => strtolower($status));

            $duplicates = $mapping->duplicates();

            if ($duplicates->isNotEmpty()) {
                $validator->errors()->add(
                    'status_mapping',
                    'Each ClickUp status can only be mapped from one app status — "'.$duplicates->first().'" is used more than once.'
                );
            }
        });
    }

    /**
     * ClickupService::resolveAppStatus() looks up an incoming ClickUp status
     * with strtolower(), so the mapping must be persisted lowercase too —
     * otherwise a custom, mixed-case ClickUp status (e.g. "Needs QA")
     * silently never matches. Overriding validated() (rather than merging
     * in passedValidation()) is what's needed here: the validator snapshots
     * its data before passedValidation() runs, so a merge() there wouldn't
     * be reflected by a later validated() call.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        $validated['status_mapping'] = collect($validated['status_mapping'] ?? [])
            ->map(fn ($status) => is_string($status) ? strtolower($status) : $status)
            ->all();

        return is_null($key) ? $validated : data_get($validated, $key, $default);
    }
}
