<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();


return $config
    // Prod dependencies used only in dev paths
    ->ignoreErrorsOnPackage('symfony/dotenv', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    //->ignoreErrorsOnPackage('zenstruck/foundry', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])

    // Unused dependencies
    // These are used in config/bundles.php, YAML configuration, or Twig templates, which are not scanned by the analyser.
    ->ignoreErrorsOnPackage('api-platform/ramsey-uuid', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('doctrine/doctrine-migrations-bundle', [ErrorType::UNUSED_DEPENDENCY]) // config/bundles.php
    ->ignoreErrorsOnExtension('ext-gd', [ErrorType::UNUSED_DEPENDENCY]) // Used by imaging libraries/functions
    ->ignoreErrorsOnExtension('ext-iconv', [ErrorType::UNUSED_DEPENDENCY]) // Used by string manipulation
    ->ignoreErrorsOnPackage('nelmio/cors-bundle', [ErrorType::UNUSED_DEPENDENCY]) // config/bundles.php
    ->ignoreErrorsOnPackage('phpdocumentor/reflection-docblock', [ErrorType::UNUSED_DEPENDENCY]) // Used by Serializer/PropertyInfo
    ->ignoreErrorsOnPackage('phpstan/phpdoc-parser', [ErrorType::UNUSED_DEPENDENCY]) // Used by Serializer/PropertyInfo
    ->ignoreErrorsOnPackage('symfony/asset', [ErrorType::UNUSED_DEPENDENCY]) // Twig 'asset()'
    ->ignoreErrorsOnPackage('symfony/doctrine-messenger', [ErrorType::UNUSED_DEPENDENCY]) // config/packages/messenger.yaml
    ->ignoreErrorsOnPackage('symfony/expression-language', [ErrorType::UNUSED_DEPENDENCY]) // Security/Routing configuration
    ->ignoreErrorsOnPackage('symfony/flex', [ErrorType::UNUSED_DEPENDENCY]) // Composer plugin
    ->ignoreErrorsOnPackage('symfony/http-client', [ErrorType::UNUSED_DEPENDENCY]) // Service configuration
    ->ignoreErrorsOnPackage('symfony/lock', [ErrorType::UNUSED_DEPENDENCY]) // Service configuration
    ->ignoreErrorsOnPackage('symfony/monolog-bundle', [ErrorType::UNUSED_DEPENDENCY]) // config/bundles.php
    ->ignoreErrorsOnPackage('symfony/property-access', [ErrorType::UNUSED_DEPENDENCY]) // Used by Form/Serializer
    ->ignoreErrorsOnPackage('symfony/property-info', [ErrorType::UNUSED_DEPENDENCY]) // Used by Serializer
    ->ignoreErrorsOnPackage('symfony/runtime', [ErrorType::UNUSED_DEPENDENCY]) // public/index.php
    ->ignoreErrorsOnPackage('symfony/twig-bundle', [ErrorType::UNUSED_DEPENDENCY]) // config/bundles.php
    ->ignoreErrorsOnPackage('twig/cssinliner-extra', [ErrorType::UNUSED_DEPENDENCY]) // Twig filters
    ->ignoreErrorsOnPackage('twig/extra-bundle', [ErrorType::UNUSED_DEPENDENCY]) // config/bundles.php
;
