<?php

namespace App\Http\Requests;

use App\Rules\ExternalUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompetitorSourceRequest extends FormRequest
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
            'url' => ['required', 'url', 'max:2048', new ExternalUrl],
            'feed_url' => ['nullable', 'url', 'max:2048', new ExternalUrl],
            'crawler_type' => ['required', 'in:rss,sitemap,scrape,api'],
            'config' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'crawl_interval_minutes' => ['integer', 'min:5', 'max:10080'],
        ];
    }
}
