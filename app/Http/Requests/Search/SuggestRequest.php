<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SuggestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'space_id' => ['sometimes', 'string', 'max:26'],
        ];
    }
}
