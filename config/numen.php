<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Numen Configuration
    | AI-First Headless CMS by byte5.labs
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Default Provider + Fallback Chain
    |--------------------------------------------------------------------------
    | When the primary provider is rate-limited or unavailable, the LLMManager
    | tries each provider in `fallback_chain` in order.
    | Supported: 'anthropic', 'openai', 'azure'
    */
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'anthropic'),

    'fallback_chain' => array_filter(explode(',', env('AI_FALLBACK_CHAIN', 'anthropic,openai,azure'))),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'anthropic' => [
            'api_key'       => env('ANTHROPIC_API_KEY'),
            'base_url'      => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-6'),
            'timeout'       => env('ANTHROPIC_TIMEOUT', 120),
        ],

        'openai' => [
            'api_key'       => env('OPENAI_API_KEY'),
            'base_url'      => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            'timeout'       => env('OPENAI_TIMEOUT', 120),
        ],

        // Azure AI Foundry (Azure OpenAI Service)
        'azure' => [
            'api_key'       => env('AZURE_OPENAI_API_KEY'),
            'endpoint'      => env('AZURE_OPENAI_ENDPOINT'),         // e.g. https://my-resource.openai.azure.com
            'api_version'   => env('AZURE_OPENAI_API_VERSION', '2024-02-01'),
            'default_model' => env('AZURE_OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            'timeout'       => env('AZURE_OPENAI_TIMEOUT', 120),
            // Map generic model names → Azure deployment names
            'deployments'   => [
                'gpt-4o'      => env('AZURE_DEPLOYMENT_GPT4O', 'gpt-4o'),
                'gpt-4o-mini' => env('AZURE_DEPLOYMENT_GPT4O_MINI', 'gpt-4o-mini'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role → Model Assignments
    | These drive which model each pipeline stage uses.
    | Format: "provider:model" or just "model" (provider auto-detected)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'generation'         => env('AI_MODEL_GENERATION', 'claude-sonnet-4-6'),
        'generation_premium' => env('AI_MODEL_GENERATION_PREMIUM', 'claude-opus-4-6'),
        'seo'                => env('AI_MODEL_SEO', 'claude-haiku-4-5-20251001'),
        'review'             => env('AI_MODEL_REVIEW', 'claude-opus-4-6'),
        'planning'           => env('AI_MODEL_PLANNING', 'claude-opus-4-6'),
        'classification'     => env('AI_MODEL_CLASSIFICATION', 'claude-haiku-4-5-20251001'),
    ],

    'cost_limits' => [
        'daily_usd'       => env('AI_COST_DAILY_LIMIT', 50.00),
        'per_content_usd' => env('AI_COST_PER_CONTENT_LIMIT', 2.00),
        'monthly_usd'     => env('AI_COST_MONTHLY_LIMIT', 500.00),
    ],

    'pipeline' => [
        'auto_publish_threshold' => env('AI_AUTO_PUBLISH_SCORE', 80),
        'human_gate_timeout_hours' => env('AI_HUMAN_GATE_TIMEOUT', 48),
        'max_retry_attempts' => 3,
        'content_refresh_days' => env('AI_CONTENT_REFRESH_DAYS', 30),
    ],

    'queues' => [
        'generation' => 'ai-pipeline',
        'transform'  => 'ai-pipeline',
        'review'     => 'ai-pipeline',
        'publishing' => 'ai-pipeline',
        'webhooks'   => 'default',
    ],

];
