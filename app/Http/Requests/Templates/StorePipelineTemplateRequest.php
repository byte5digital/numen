<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class StorePipelineTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_url' => ['nullable', 'url', 'max:500'],
            'definition' => ['required', 'array'],
            'definition.schema_version' => ['required', 'string'],
            'definition.stages' => ['required', 'array', 'min:1'],
            'definition.settings' => ['nullable', 'array'],
            'definition.personas' => ['nullable', 'array'],
            'version' => ['nullable', 'string', 'max:50'],
            'changelog' => ['nullable', 'string'],
        ];
    }
}
