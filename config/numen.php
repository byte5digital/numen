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
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-6'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 120),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-5-mini'),
            'timeout' => env('OPENAI_TIMEOUT', 120),
        ],

        // Azure AI Foundry (Azure OpenAI Service)
        'azure' => [
            'api_key' => env('AZURE_OPENAI_API_KEY'),
            'endpoint' => env('AZURE_OPENAI_ENDPOINT'),         // e.g. https://my-resource.openai.azure.com
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-01'),
            'default_model' => env('AZURE_OPENAI_DEFAULT_MODEL', 'gpt-5-mini'),
            'timeout' => env('AZURE_OPENAI_TIMEOUT', 120),
            // Map generic model names → Azure deployment names
            'deployments' => [
                'gpt-5-mini' => env('AZURE_DEPLOYMENT_GPT5_MINI', 'gpt-5-mini'),
                'gpt-5-nano' => env('AZURE_DEPLOYMENT_GPT5_NANO', 'gpt-5-nano'),
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
        'generation' => env('AI_MODEL_GENERATION', 'claude-sonnet-4-6'),
        'generation_premium' => env('AI_MODEL_GENERATION_PREMIUM', 'claude-opus-4-6'),
        'seo' => env('AI_MODEL_SEO', 'claude-haiku-4-5-20251001'),
        'review' => env('AI_MODEL_REVIEW', 'claude-opus-4-6'),
        'planning' => env('AI_MODEL_PLANNING', 'claude-opus-4-6'),
        'classification' => env('AI_MODEL_CLASSIFICATION', 'claude-haiku-4-5-20251001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation Providers
    |--------------------------------------------------------------------------
    | Multi-provider image generation. Each provider can be selected per-persona
    | via model_config.generator_provider + model_config.generator_model.
    | Falls back to default_image_provider when no persona config is set.
    */
    'image_providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_IMAGE_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('IMAGE_MODEL_OPENAI', 'gpt-image-1'),
        ],

        'together' => [
            'api_key' => env('TOGETHER_API_KEY'),
            'base_url' => env('TOGETHER_BASE_URL', 'https://api.together.xyz/v1'),
            'default_model' => env('IMAGE_MODEL_TOGETHER', 'black-forest-labs/FLUX.1-schnell'),
        ],

        'fal' => [
            'api_key' => env('FAL_API_KEY'),
            'base_url' => env('FAL_BASE_URL', 'https://fal.run'),
            'default_model' => env('IMAGE_MODEL_FAL', 'fal-ai/flux/schnell'),
        ],

        'replicate' => [
            'api_key' => env('REPLICATE_API_KEY'),
            'base_url' => env('REPLICATE_BASE_URL', 'https://api.replicate.com/v1'),
            'default_model' => env('IMAGE_MODEL_REPLICATE', 'black-forest-labs/flux-2-max'),
        ],

    ],

    'default_image_provider' => env('DEFAULT_IMAGE_PROVIDER', 'openai'),

    'cost_limits' => [
        'daily_usd' => env('AI_COST_DAILY_LIMIT', 50.00),
        'per_content_usd' => env('AI_COST_PER_CONTENT_LIMIT', 2.00),
        'monthly_usd' => env('AI_COST_MONTHLY_LIMIT', 500.00),
    ],

    'pipeline' => [
        'auto_publish_threshold' => env('AI_AUTO_PUBLISH_SCORE', 80),
        'human_gate_timeout_hours' => env('AI_HUMAN_GATE_TIMEOUT', 48),
        'max_retry_attempts' => 3,
        'content_refresh_days' => env('AI_CONTENT_REFRESH_DAYS', 30),
    ],

    'queues' => [
        'generation' => 'ai-pipeline',
        'transform' => 'ai-pipeline',
        'review' => 'ai-pipeline',
        'publishing' => 'ai-pipeline',
        'webhooks' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Taxonomy Settings
    |--------------------------------------------------------------------------
    */
    'taxonomy' => [
        'auto_assign_threshold' => env('NUMEN_TAXONOMY_AUTO_ASSIGN_THRESHOLD', 0.7),
        'auto_assign_max_terms' => env('NUMEN_TAXONOMY_AUTO_ASSIGN_MAX', 5),
        'categorization_model' => env('NUMEN_TAXONOMY_MODEL', 'claude-haiku-4-5-20251001'),
        'categorization_provider' => env('NUMEN_TAXONOMY_PROVIDER', 'anthropic'),
        'slug_separator' => '-',
        'slug_max_length' => 255,
        'max_depth' => env('NUMEN_TAXONOMY_MAX_DEPTH', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    */
    'storage_disk' => env('FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    | Three-tier AI-powered search: Instant (Meilisearch) + Semantic (pgvector) + Ask (RAG)
    */
    'search' => [
        'embedding_provider' => env('SEARCH_EMBEDDING_PROVIDER', 'openai'),
        'embedding_model' => env('SEARCH_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'embedding_dimensions' => env('SEARCH_EMBEDDING_DIMENSIONS', 1536),
        'meilisearch_host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'meilisearch_key' => env('MEILISEARCH_KEY'),
        'rag_model' => env('SEARCH_RAG_MODEL', 'claude-haiku-4-5-20251001'),
        'rag_provider' => env('SEARCH_RAG_PROVIDER', 'anthropic'),
        'rag_max_context_tokens' => env('SEARCH_RAG_MAX_CONTEXT', 4000),
        'rag_max_sources' => env('SEARCH_RAG_MAX_SOURCES', 5),
        'chunk_max_tokens' => env('SEARCH_CHUNK_MAX_TOKENS', 512),
        'chunk_overlap_tokens' => env('SEARCH_CHUNK_OVERLAP', 64),
        'tiers_enabled' => [
            'instant' => env('SEARCH_TIER_INSTANT', true),
            'semantic' => env('SEARCH_TIER_SEMANTIC', true),
            'ask' => env('SEARCH_TIER_ASK', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge Graph Configuration
    |--------------------------------------------------------------------------
    */
    'graph' => [
        'enabled' => env('GRAPH_ENABLED', true),
        'similarity_threshold' => (float) env('GRAPH_SIMILARITY_THRESHOLD', 0.75),
        'max_edges_per_type' => (int) env('GRAPH_MAX_EDGES_PER_TYPE', 20),
        'queue' => env('GRAPH_QUEUE', 'graph'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Competitor Analysis
    |--------------------------------------------------------------------------
    | Controls the competitor-aware content differentiation pipeline stage.
    */
    'competitor_analysis' => [
        'enabled' => env('COMPETITOR_ANALYSIS_ENABLED', true),
        'similarity_threshold' => (float) env('COMPETITOR_SIMILARITY_THRESHOLD', 0.25),
        'max_competitors_to_analyze' => (int) env('COMPETITOR_MAX_ANALYZE', 5),
        'auto_enrich_briefs' => env('COMPETITOR_AUTO_ENRICH_BRIEFS', true),
    ],

];
