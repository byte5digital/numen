<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class CreateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:50'],
            'definition' => ['required', 'array'],
            'definition.schema_version' => ['required', 'string'],
            'definition.stages' => ['required', 'array', 'min:1'],
            'definition.settings' => ['nullable', 'array'],
            'definition.personas' => ['nullable', 'array'],
            'changelog' => ['nullable', 'string'],
        ];
    }
}
