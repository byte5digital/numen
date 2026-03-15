<?php

namespace App\Http\Requests;

use App\Services\FormatAdapterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepurposeContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth handled by sanctum middleware
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'format_key' => ['required', 'string', Rule::in(app(FormatAdapterService::class)->getSupportedFormats()->keys())],
            'persona_id' => 'nullable|integer|exists:personas,id',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'format_key.in' => 'Unsupported format. Supported: twitter_thread, linkedin_post, newsletter_section, instagram_caption, podcast_script_outline, product_page_copy, faq_section, youtube_description',
        ];
    }
}
