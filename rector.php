<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/bin',
        __DIR__ . '/public/index.php',
        __DIR__ . '/src',
    ]);
    $rectorConfig->sets([LevelSetList::UP_TO_PHP_83]);
};
