<?php

declare(strict_types=1);

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class MigrationDetectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
        ];
    }
}
