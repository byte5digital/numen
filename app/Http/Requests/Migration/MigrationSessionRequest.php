<?php

declare(strict_types=1);

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class MigrationSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PATCH') || $this->isMethod('PUT');

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'source_url' => [$isUpdate ? 'sometimes' : 'required', 'url', 'max:2048'],
            'source_cms' => [$isUpdate ? 'sometimes' : 'required', 'string', 'in:wordpress,strapi,payload,contentful,ghost,directus'],
            'source_version' => ['nullable', 'string', 'max:50'],
            'credentials' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
        ];
    }
}
