<?php

namespace App\Services\Statamic;

use Statamic\Updater\UpdatesOverview;
class SafeUpdatesOverview extends UpdatesOverview
{
    protected function checkAndCache()
    {
        // The CP requests this on every page load. Avoid remote Marketplace
        // lookups locally because an unlicensed addon can make the badge fail.
        return $this->resetState()->cache();
    }
}
