<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
            'fallback_locale' => 'nullable|string|max:10',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }
}
