<?php

namespace App\Command;

use App\Service\CsvService;
use Doctrine\Common\Collections\Collection;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
  name: 'serializer:validate:csv',
  description: 'Validation des CSV custom',
)]
class SerializerValidateCsv extends Command {
  private OutputInterface $output;
  private SymfonyStyle $io;
  private array $entities = [];
  private array $customCsvConfigs = [];

  public function __construct(
    private readonly KernelInterface $kernel,
    private readonly CsvService $csvService,
  ) {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $hasError = false;
    $this->io = new SymfonyStyle($input, $output);
    $this->output = $output;

    $this->extractAppEntities();
    $this->customCsvConfigs = $this->csvService->getCustomCsvConfigs();

    if ($this->validateEntities()) $hasError = true;
    if ($this->validateCustomCsv()) $hasError = true;


    $this->output->write("\n");
    return $hasError ? 1 : 0;
  }

  /**
   * @return bool false if errors
   */
  private function validateEntities(): bool {
    $hasError = false;
    $this->io->section("Checking entities exists");

    $csvCustomEntities = array_keys($this->customCsvConfigs);

    foreach ($csvCustomEntities as $csvCustomEntity) {
      if (!array_key_exists((string) $csvCustomEntity, $this->entities)) {
        $this->io->newLine();
        $this->io->error("No matching entity found for $csvCustomEntity");
        $hasError = true;
      }
    }

    if (!$hasError) $this->io->success("");

    return $hasError;
  }

  private function validateCustomCsv(): bool {
    $hasError = false;
    $this->io->section("Validating custom csv mapping");

    foreach ($this->customCsvConfigs as $targetEntity => $csvConfig) {
      $this->io->comment($targetEntity);
      $colNames = [];
      foreach ($csvConfig as $configField => $configValue) {
        if ($this->validateCustomConfigLine($targetEntity, $configField, $configValue, $colNames)) $hasError = true;
      }
      if (!$hasError) $this->io->writeln("<info>Everything is ok.</info>");
    }

    if (!$hasError) $this->io->success("");

    return $hasError;
  }

  private function validateCustomConfigLine($targetEntity, $configField, $configValue, array &$colNames): bool {
    $hasError = false;

    // We get the field from entity
    /** @var ReflectionClass $reflectionClass */
    $reflectionClass = $this->entities[$targetEntity];

    try {
      $reflectionProperty = $reflectionClass->getProperty($configField);

      if (is_array($configValue)) {
        if (!array_key_exists("fields", $configValue)) {
          $this->io->error("\"fields\" property is required for $configField");
          $hasError = true;
        }

        if (!$this->validateChildrenProperty($reflectionProperty, $configValue["fields"], $configField)) $hasError = true;
      }

      if (!$this->registerUsedColName($colNames, $configField, $configValue)) $hasError = true;

    } catch (ReflectionException $e) {
      $this->io->error($e->getMessage());
      $hasError = true;
    }

    return $hasError;
  }

  private function registerUsedColName(array &$colNames, $configField, $configValue): bool {
    $success = true;

    if (is_array($configValue)) {
      if (array_key_exists("prefix", $configValue)) {
        // We register the prefix
        if (!$this->registerField($colNames, $configValue["prefix"], $configField)) $success = false;
        // We check all field inside as a new config

        $childMapping = [];
        foreach ($configValue["fields"] as $field => $value) {
          if (!$this->registerUsedColName($childMapping, $field, $value)) $success = false;
        }
        $colNames[$configValue["prefix"]] = $childMapping;
      } else {
        foreach ($configValue["fields"] as $field => $value) {
          if (!$this->registerUsedColName($colNames, $field, $value)) $success = false;
        }
      }
    } else {
      $fieldName = $configValue ?? $configField;
      if (!$this->registerField($colNames, $fieldName, $configField)) $success = false;
    }

    return $success;
  }

  private function validateChildrenProperty(ReflectionProperty $reflectionProperty, array $propCsvConfig, string $parent): bool {
    $success = true;

    // We first try to find if we have a matching entity class

    $matchedClass = null;
    $reflectionPropName = $reflectionProperty->getType()->getName();
    if (array_key_exists((string) $reflectionPropName, $this->entities)) {
      $matchedClass = $reflectionPropName;
    } else {
      if ($reflectionPropName === Collection::class) {
        foreach ($reflectionProperty->getAttributes() as $attribute) {
          if (array_key_exists("targetEntity", $attribute->getArguments())){
            $targetEntity = $attribute->getArguments()["targetEntity"];
            if (array_key_exists((string) $targetEntity, $this->entities)) {
              $matchedClass = $targetEntity;
            }
          }
        }
      }
    }

    if (!$matchedClass) {
      $this->io->error("Couldn't find any entity class that match with the property {$reflectionProperty->name}. From $parent.");
      return false;
    }

    // We get the field from entity
    /** @var ReflectionClass $reflectionClass */
    $reflectionClass = $this->entities[$matchedClass];

    foreach ($propCsvConfig as $configField => $configValue) {
      try {
        $reflectProp = $reflectionClass->getProperty($configField);

        if (is_array($configValue)) {
          if (!$this->validateChildrenProperty($reflectProp, $configValue["fields"], "$parent.$configField")) $success = false;
        }
      } catch (ReflectionException $e) {
        $this->io->error($e->getMessage() . ". From $parent");
        $success = false;
      }
    }

    return $success;
  }






  private function extractAppEntities(): void {
    $this->io->comment("Extracting app entities");
    // We get all registered entities
    $finder = new Finder();
    $finder->files()->in($this->kernel->getProjectDir() . "/src/Entity")
           ->notName("/(^Abstract)|(Interface\.php$)/m"); // Not start with abstract or end with Interface.php

    $progress = new ProgressBar($this->output, count($finder));
    $progress->start();
    foreach ($finder as $file) {
      $namespace = "App\\Entity"; // The root namespace
      // For the subfolder, we generate the right namespace
      if (!empty($file->getRelativePath())) {
        $namespace .= "\\" . str_replace("/", "\\", $file->getRelativePath());
      }

      $classname = $namespace . "\\" . $file->getBasename(".php");
      $reflexionClass = new ReflectionClass($classname);
      // We save the mapping for later use case
      $this->entities[$classname] = $reflexionClass;
      $progress->advance();
    }
    $progress->clear();
    $this->io->newLine(2);
  }

  /**
   * @param array $fields
   * @param string $fieldName
   * @param string $yamlProp
   * @return bool true = registered
   */
  private function registerField(array &$fields, string $fieldName, string $yamlProp): bool {
    if (array_key_exists($fieldName, $fields)) {
      $this->io->error("Field \"$fieldName\" is already declared. (yaml prop: $yamlProp. Remember to check also in child declaration if you don't find the field)");
      return false;
    }

    $fields[$fieldName] = $fieldName;

    return true;
  }
}
