<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_id' => ['required', 'string'],
            'event_type' => ['required', 'string', 'in:view,engagement,conversion,bounce,scroll_depth,time_on_page'],
            'source' => ['required', 'string', 'in:pixel,webhook,api,sdk'],
            'value' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
            'session_id' => ['required', 'string'],
            'visitor_id' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
