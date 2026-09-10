<?php

use Rector\Config\RectorConfig;
use Rector\Transform\Rector\String_\StringToClassConstantRector;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php83: true)
    ->withSets([LaravelLevelSetList::UP_TO_LARAVEL_130])
    // hält View-Namen wie 'auth.login' für Event-Klassen
    ->withSkip([StringToClassConstantRector::class]);
