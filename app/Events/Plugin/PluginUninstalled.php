<?php

namespace App\Events\Plugin;

use App\Models\Plugin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PluginUninstalled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Plugin $plugin,
    ) {}
}
