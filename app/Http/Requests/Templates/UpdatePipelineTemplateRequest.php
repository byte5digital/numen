<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePipelineTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
