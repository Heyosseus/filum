<?php

declare(strict_types=1);

use Heyosseus\Filum\Tests\FilamentTestCase;
use Heyosseus\Filum\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Unit', __DIR__.'/Feature');

// Anything that mounts a component or a page needs a real panel behind it.
uses(FilamentTestCase::class)->in(__DIR__.'/Filament');
