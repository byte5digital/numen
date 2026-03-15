<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'space_id' => 'required|string|exists:spaces,id',
            'locale' => 'required|string|max:10|regex:/^[a-zA-Z]{2,3}(-[a-zA-Z]{2,4})?$/',
            'label' => 'required|string|max:100',
            'is_default' => 'boolean',
            'fallback_locale' => 'nullable|string|max:10',
            'sort_order' => 'integer|min:0',
        ];
    }
}
