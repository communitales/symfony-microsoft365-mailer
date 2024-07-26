<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/Tests',
        __DIR__.'/Transport',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODING_STYLE,
        SetList::CODE_QUALITY,
    ])
    ->withTypeCoverageLevel(0)
    ->withImportNames();
