<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Services\Migration\Connectors\CmsConnectorInterface;
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
            default => throw new InvalidArgumentException(
                "Unsupported CMS: {$cms}. Supported types: wordpress"
            ),
        };
    }
}
