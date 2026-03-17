<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

interface CmsConnectorInterface
{
    /**
     * Retrieve content types from the external CMS.
     *
     * @return array<array-key, mixed>
     */
    public function getContentTypes(): array;
}
