<?php

namespace App\Http\Requests;

use App\Rules\ExternalUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompetitorAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:new_content,keyword,high_similarity'],
            'conditions' => ['nullable', 'array'],
            'conditions.keywords' => ['sometimes', 'array'],
            'conditions.keywords.*' => ['string', 'max:100'],
            'conditions.similarity_threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'conditions.source_id' => ['sometimes', 'string', 'exists:competitor_sources,id'],
            'is_active' => ['boolean'],
            'notify_channels' => ['nullable', 'array'],
            'notify_channels.email' => ['sometimes', 'array'],
            'notify_channels.email.*' => ['email'],
            'notify_channels.slack_webhook' => ['sometimes', 'url', 'max:2048', new ExternalUrl],
            'notify_channels.webhook_url' => ['sometimes', 'url', 'max:2048', new ExternalUrl],
        ];
    }
}
