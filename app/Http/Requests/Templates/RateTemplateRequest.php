<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class RateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
