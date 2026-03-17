<?php

declare(strict_types=1);

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class MigrationMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.source_type_key' => ['required', 'string', 'max:255'],
            'mappings.*.source_type_label' => ['nullable', 'string', 'max:255'],
            'mappings.*.numen_content_type_id' => ['nullable', 'string', 'max:26'],
            'mappings.*.numen_type_slug' => ['nullable', 'string', 'max:255'],
            'mappings.*.field_map' => ['required', 'array'],
            'mappings.*.status' => ['sometimes', 'string', 'in:pending,approved,skipped'],
        ];
    }
}
