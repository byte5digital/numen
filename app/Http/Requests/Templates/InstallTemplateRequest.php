<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class InstallTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'variable_values' => ['nullable', 'array'],
            'config_overrides' => ['nullable', 'array'],
        ];
    }
}
