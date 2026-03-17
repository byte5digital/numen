<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

interface CmsConnectorInterface
{
    /**
     * Retrieve content types from the external CMS.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getContentTypes(): array;
}
