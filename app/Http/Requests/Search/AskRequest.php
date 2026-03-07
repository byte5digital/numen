<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class AskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:500'],
            'conversation_id' => ['sometimes', 'nullable', 'string', 'max:26'],
            'space_id' => ['sometimes', 'string', 'max:26'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
