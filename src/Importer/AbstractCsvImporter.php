<?php declare(strict_types=1);

namespace App\Importer;

use App\Enum\ImportException;
use App\Importer\Model\AbstractImportedItemResult;
use App\Importer\Model\SuccessImportedItem;
use App\Importer\Model\WarningImportedItem;
use App\Service\UtilsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractCsvImporter {

  private ?array $headers = null;

  protected ?array $currentRow = null;

  protected int $everyN = 100;

  public function __construct(
    protected EntityManagerInterface $em,
    protected ValidatorInterface $validator,
  ) {
  }

  protected function getRequiredCols(): array {
    return [];
  }

  /**
   * This function is called for every line of the csv
   * @param array $row
   * @return AbstractImportedItemResult
   */
  abstract protected function addItem(array &$row): AbstractImportedItemResult;

  protected function callbackHeaderRowParsed(): void {}
  protected function callbackBeforeRowsParsed(): void {}
  protected function callbackAfterRowsParsed(): void {}
  /** Callback mostly used for memory optimisation when dealing with huge dataset */
  protected function callbackEveryNParsedRows(): void {}

  /**
   * @param File $file
   * @param string $delimiter
   * @return array
   */
  public function fromFile(File $file, string $delimiter = ","): array {
    if (!$file->isReadable()) {
      $result["errors"][] = $this->formatError(ImportException::FILE_NOT_READABLE->value);
      return $result;
    }

    /** @var \SplFileObject $fo */
    $fo = $file->openFile("r");
    $fo->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);
    $fo->setCsvControl($delimiter, '"', "\\");

    $rows = [];
    while (!$fo->eof()) {
      // Row initialisation
      $row = $fo->fgetcsv();
      if (!$row) continue;
      $rows[] = $row;
    }
    return $this->parse($rows);
  }

  public function fromBody(string $content, string $delimiter = ",") {
    $content = trim($content);
    $rows = str_getcsv($content, "\n");

    $realRows = [];
    foreach ($rows as $row) {
      $realRows[] = str_getcsv((string) $row, $delimiter);
    }
    return $this->parse($realRows);
  }

  public function fromArrayWKeys(array $rows): array {
    if (empty($rows)) {
      return [];
    }

    // We get the "header" from the key
    $keys = array_keys($rows[0]);
    $this->headers = array_combine($keys, $keys);
    return $this->parse($rows);
  }

  private function parse(array $rows): array {
    $result = [
      "errors"  => [],
      "warnings"  => [],
      "created" => [],
    ];

    $this->callbackBeforeRowsParsed();
    $lineNumber = 0;
    foreach ($rows as $row) {
      ++$lineNumber;
      $this->parseRow($lineNumber, $row, $result);
      $this->startEveryNCallback($lineNumber);
    }
    $this->callbackAfterRowsParsed();
    return $result;
  }


  protected function formatImportItemResult(string $state, string $message = ""): array {
    return [$state, $message];
  }

  protected function hasKey(string $key): bool {
    return in_array($key, $this->headers);
  }

  private function getMultiColNames(string $key): array {
    return preg_grep('/^'.$key.'\..*$/', $this->headers);
  }

  protected function hasKeyMultiCol(string $key): bool {
    return !empty($this->getMultiColNames($key));
  }

  protected function getHeaderIndex(string $key) {
    foreach ($this->headers as $k => $v) {
      if ($v === $key) return $k;
    }
    return null;
  }

  protected function getHeaders(): ?array {
    return $this->headers;
  }

  protected function getValue(array $row, string $colName): ?string {
    if ($this->hasKey($colName) && // Col declared in the general header
        array_key_exists($this->getHeaderIndex($colName), $row)) { // Col is present in our line
      return trim((string) $row[$this->getHeaderIndex($colName)]);
    }
    return null;
  }

  protected function getMultiValue(array $row, string $colName): ?array {
    if (!$this->hasKeyMultiCol($colName)) return null;
    $cols = $this->getMultiColNames($colName);

    $values = [];
    foreach ($cols as $col) {
      $colValue = $this->getValue($row, $col);

      // preg_match('/^'.$colName.'\.(\d*)\.(.*)$/', $col, $matches);
      $fieldNames = explode(".", (string) $col, 3);
      $values[$fieldNames[1]][$fieldNames[2]] = $colValue;

    }

    if (empty($values)) return null;

    return $values;
  }

  protected function getCurrentRowValue(string $colName): ?string {
    return $this->getValue($this->currentRow, $colName);
  }

  protected function getCurrentRowMultiValue(string $colName): ?array {
    return $this->getMultiValue($this->currentRow, $colName);
  }

  /**
   * @param string $colName
   * @param \Closure $callback Will be call with one argument, the value it get from the file
   * @param bool $isOptional Define to true, will only call the callback if the value is !empty()
   * @return bool|void Return the callback response (either void, or a boolean)
   */
  protected function callbackForRowCol(string $colName, \Closure $callback, bool $isOptional = true) {
    $value = $this->getCurrentRowValue($colName);
    if ($isOptional && empty($value)) return; // Field is empty we don't call the method
    return $callback($value);
  }

  /**
   * @param string $colName
   * @param \Closure $callback Will be call with one argument, the values it got from the file
   * @return bool|void Return the callback response (either void, or a boolean)
   */
  protected function callbackForRowMultiCol(string $colName, \Closure $callback) {
    $values = $this->getCurrentRowMultiValue($colName);
    return $callback($values);
  }

  /**
   * @param string $type the check that is made, can be int, bool
   *
   * @return mixed the value sanitized
   */
  protected function sanitizeColValueForNumber(mixed $value, string $type = "int") {
    if ($type === "int") {
      if ($value === "") $value = null;
      if (!is_null($value) && !is_numeric($value)) {
        return null;
      }
    } elseif ($type === "bool") {
      if (!is_bool($value) && !is_null($value)) {
        return $this->toBoolean($value);
      } else {
        return false;
      }
    }
    return $value;
  }

  /**
   * Convert the passed value to boolean.
   * Will be `false` if value is incorrect
   *
   * @param $value
   * @return bool
   */
  protected function toBoolean($value): bool {
    return UtilsService::toBoolean($value);
  }

  private function parseRow(int $lineNumber, array &$row, array &$result): void {
    $this->sanitizeCols($row);

    if (!$this->headers) { // First line is the header
      $this->headers = $row;
      $this->callbackHeaderRowParsed();
      return;
    }

    // If we got defined required cols, we check it
    if (!empty($this->getRequiredCols())) {
      foreach ($this->getRequiredCols() as $required) {
        if (!$this->hasKey($required)) {
          $result["errors"][] = $this->formatArrayError($lineNumber, [
            "errorCode" => "col-missing-required",
            "reason" => ImportException::COL_MISSING_REQUIRED->value,
            "errorValue" => $required,
          ]);
          return;
        }
      }
    }

    // If we got defined required cols, we check it
    if (!empty($this->getRequiredCols())) {
      foreach ($this->getRequiredCols() as $required) {
        if (!$this->hasKey($required)) {
          $result["errors"][] = $this->formatArrayError($lineNumber, [
            "errorCode" => "col-missing-required",
            "reason" => ImportException::COL_MISSING_REQUIRED->value,
            "errorValue" => $required,
          ]);
          return;
        }
      }
    }

    // Row parsing
    $this->currentRow = $row;
    $state = $this->addItem($row);
    $this->updateResultFromAddItemResult($result, $lineNumber, $state);
  }

  private function updateResultFromAddItemResult(array &$result, int $lineNumber, AbstractImportedItemResult $state): void {
      if ($state instanceof SuccessImportedItem) {
        $result["created"][] = $state->format($lineNumber);
      } elseif ($state instanceof WarningImportedItem) {
        $result["warnings"][] = $state->format($lineNumber);
      } else {
        $result["errors"][] = $state->format($lineNumber);
      }
  }


  private function startEveryNCallback($lineNumber): void {
    if ($lineNumber && ($lineNumber % $this->everyN === 0)) $this->callbackEveryNParsedRows();
  }

  private function sanitizeCols(array &$row): void {
    // http://en.wikipedia.org/wiki/Byte_order_mark#UTF-8
    $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
    foreach ($row as $k => $col) {
      if (substr((string) $col, 0, 3) === $bom) {
        $col = substr((string) $col, 3);
      }
      $row[$k] = trim((string) $col);
    }
  }

  private function formatError(string $message, ?int $lineNumber = null, ?string $explanation = null): array {
    $resp = ["message" => $message, "lineNumber" => $lineNumber];
    if ($explanation) $resp["explanation"] = $explanation;
    return $resp;
  }

  private function formatArrayError(int $lineNumber, array $errorLine): array {
    return array_merge(["errorLine" => $lineNumber], $errorLine);
  }
}
