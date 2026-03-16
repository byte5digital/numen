<?php

namespace App\Http\Requests;

use App\Rules\ExternalUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetitorSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:2048', new ExternalUrl],
            'feed_url' => ['nullable', 'url', 'max:2048', new ExternalUrl],
            'crawler_type' => ['sometimes', 'in:rss,sitemap,scrape,api'],
            'config' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'crawl_interval_minutes' => ['sometimes', 'integer', 'min:5', 'max:10080'],
        ];
    }
}
