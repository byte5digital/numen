<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:500'],
            'mode' => ['sometimes', 'string', 'in:instant,semantic,hybrid'],
            'type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
            'taxonomy' => ['sometimes', 'array'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'highlight' => ['sometimes', 'boolean'],
            'space_id' => ['sometimes', 'string', 'max:26'],
        ];
    }
}
