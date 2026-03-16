<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Services\Migration\Connectors\CmsConnectorInterface;
use App\Services\Migration\Connectors\ContentfulConnector;
use App\Services\Migration\Connectors\DirectusConnector;
use App\Services\Migration\Connectors\GhostConnector;
use App\Services\Migration\Connectors\PayloadConnector;
use App\Services\Migration\Connectors\StrapiConnector;
use App\Services\Migration\Connectors\WordPressConnector;
use InvalidArgumentException;

class CmsConnectorFactory
{
    /**
     * Create a CMS connector for the given CMS type.
     *
     * @param  array<string, mixed>|null  $credentials
     *
     * @throws InvalidArgumentException
     */
    public function make(string $cms, string $url, ?array $credentials = null): CmsConnectorInterface
    {
        return match (strtolower($cms)) {
            'wordpress', 'wp' => new WordPressConnector(
                url: $url,
                username: $credentials['username'] ?? null,
                password: $credentials['password'] ?? null,
            ),
            'strapi' => new StrapiConnector(
                url: $url,
                token: $credentials['token'] ?? '',
            ),
            'payload', 'payloadcms' => new PayloadConnector(
                url: $url,
                apiKey: $credentials['api_key'] ?? '',
            ),
            'contentful' => new ContentfulConnector(
                spaceId: $credentials['space_id'] ?? $url,
                accessToken: $credentials['access_token'] ?? '',
                environment: $credentials['environment'] ?? 'master',
            ),
            'ghost' => new GhostConnector(
                url: $url,
                apiKey: $credentials['api_key'] ?? '',
            ),
            'directus' => new DirectusConnector(
                url: $url,
                token: $credentials['token'] ?? '',
            ),
            default => throw new InvalidArgumentException(
                "Unsupported CMS: {$cms}. Supported types: wordpress, strapi, payload, contentful, ghost, directus"
            ),
        };
    }
}
