<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Parser;

class CsvService {
  private array $customCsvConfigs = [];

  public function __construct(
    private readonly KernelInterface $kernel,
  ) {
  }

  public function getCustomCsvConfigs(): array {
    if (!empty($this->customCsvConfigs)) {
      return $this->customCsvConfigs;
    }

    $configs = [];
    // We load the files
    $finder = new Finder();
    $finder->files()
           ->in($this->kernel->getProjectDir() . "/config/normalizer_csv")
           ->name(["*.yml", "*.yaml"]);

    $yamlParser = new Parser();
    /** @var SplFileInfo $file */
    foreach ($finder as $file) {
      $configs = array_merge($configs, $yamlParser->parseFile($file->getPathname()));
    }

    $this->customCsvConfigs = $configs;

    return $this->customCsvConfigs;
  }

  /**
   * Normalize the item, the passed classname must be present in self::getCustomCsvConfigs()
   *
   * @param string $classname
   * @param array $item
   */
  public function normalize(string $classname, array &$item): void {
    $configs = $this->getCustomCsvConfigs();
    if (!array_key_exists($classname, $configs)) {
      return;
    }

    $this->doNormalize($configs[$classname], $item);
  }

  private function doNormalize(array $csvConfig, array &$item): void {
    // Check if recursive array
    // Sometimes we could be in a sequential state
    if ($this->isSequentialArray($item)) {
      foreach ($item as &$itemSequential) {
        $this->doNormalize($csvConfig, $itemSequential);
      }
      return;
    }

    foreach (array_keys($item) as $name) {
      // Remove undeclared fields
      if (!array_key_exists((string) $name, $csvConfig)) {
        unset($item[$name]);
        continue;
      }

      $itemConfig = $csvConfig[$name];
      // If the value is a not an array in the config we know we can apply the new name
      if (!is_array($itemConfig)) {
        if (is_null($itemConfig)) {
          continue;
        } else { // We have to rename the field
          $this->renameKey($item, $name, $itemConfig);

        }
      } else { // We are in an array configuration, we'll have a recursive call
        if (array_key_exists("prefix", $itemConfig)) {
          $this->renameKey($item, $name, $itemConfig["prefix"]);
          $itemNewKey = $itemConfig["prefix"];

          $newFields = $item[$itemNewKey]; // We copy

          $this->doNormalize($csvConfig[$name]["fields"], $newFields);
          $item[$itemNewKey] = $newFields;
        } else {
          $newFields = $item[$name]; // We copy
          unset($item[$name]); // We remove the field, since we don't want prefixing (will be put at the parent level)

          $this->doNormalize($csvConfig[$name]["fields"], $newFields);

          $item = array_merge($item, $newFields);
        }
      }
    }
  }

  private function renameKey(&$array, $oldKey, $newKey): void {
    $keys = array_keys($array);
    $index = array_search($oldKey, $keys, true);
    $keys[$index] = $newKey;
    $array = array_combine($keys, array_values($array));
  }

  private function isSequentialArray(array $array): bool {
    return !(array_keys($array) !== range(0, count($array) - 1));
  }
}
