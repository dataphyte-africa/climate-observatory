<?php

namespace Tests\Feature\Cp;

use App\Services\Statamic\SafeUpdatesOverview;
use Statamic\Updater\UpdatesOverview;
use Tests\TestCase;

class StatamicUpdatesOverviewTest extends TestCase
{
    public function test_statamic_update_checks_use_the_resilient_overview(): void
    {
        $this->assertInstanceOf(SafeUpdatesOverview::class, app(UpdatesOverview::class));
    }
}
