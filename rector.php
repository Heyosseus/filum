<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
        AddOverrideAttributeToOverriddenMethodsRector::class,
    ])
    // #[Override] is a promise about a parent that Filum does not control: the
    // same method can differ between Filament 4 and Filament 5, and the attribute
    // turns such a difference into a fatal error rather than something Compat can
    // absorb. So it is applied to Filum's own hierarchies and not to the classes
    // it inherits from Filament or Livewire.
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class => [
            __DIR__.'/src/Pages',
            __DIR__.'/src/Livewire',
        ],
    ]);
