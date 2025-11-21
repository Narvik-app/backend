<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
    ])
    // PHP 8.4+ sets
    ->withPhpSets(php84: true)
    // Symfony 7.3 configuration
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    // Composer-based detection will automatically handle Doctrine, Symfony, and API Platform upgrades
    ->withComposerBased(doctrine: true, symfony: true)
    ->withSets([
        // PHP 8.4 level sets
        LevelSetList::UP_TO_PHP_84,
        // Doctrine 3.3 improvements
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ])
    ->withRules([
        // Type declaration improvements
        AddVoidReturnTypeWhereNoReturnRector::class,
        // Code quality improvements
        InlineConstructorDefaultToPropertyRector::class,
        UnusedForeachValueToArrayKeysRector::class,
    ])
    ->withSkip([
        // Skip rector on migrations as they should remain as-is
        '*/migrations/*',
    ]);
