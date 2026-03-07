<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class ClickRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:500'],
            'content_id' => ['required', 'string', 'max:26'],
            'position' => ['required', 'integer', 'min:1'],
            'space_id' => ['sometimes', 'string', 'max:26'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
