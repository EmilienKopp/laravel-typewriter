<?php

declare(strict_types=1);

namespace Splitstack\Typewriter\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splitstack\Typewriter\TypewriterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TypewriterServiceProvider::class,
        ];
    }
}
