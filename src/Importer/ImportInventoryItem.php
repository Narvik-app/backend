<?php

namespace App\Importer;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\InventoryCategory;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Importer\Model\AbstractImportedItemResult;
use App\Importer\Model\SuccessImportedItem;
use App\Importer\Model\WarningImportedItem;
use App\Repository\ClubDependent\Plugin\Sale\InventoryCategoryRepository;
use App\Repository\ClubDependent\Plugin\Sale\InventoryItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportInventoryItem extends AbstractCsvImporter {
  private Club $club;

  private array $createdCategories = [];

  public const string COL_NAME = 'name';
  public const string COL_SELLING_PRICE = 'sellingPrice';

  public const string COL_DESCRIPTION = 'description';
  public const string COL_PURCHASE_PRICE = 'purchasePrice';
  public const string COL_CAN_BE_SOLD = 'canBeSold';
  public const string COL_SELLING_QUANTITY = 'sellingQuantity';
  public const string COL_QUANTITY = 'quantity';
  public const string COL_QUANTITY_ALERT = 'quantityAlert';
  public const string COL_BARCODE = 'barcode';
  public const string COL_CATEGORY_NAME = 'category.name';


  public const array ERROR_CODES = [
    // 1xx: Error
    //100 => ["errorCode" => "member-not-found", "reason" => "Member not found"],

    // 2xx: Warning
    200 => ["errorCode" => "item-already-registered", "reason" => "Item already registered. Safely ignoring it."],
  ];

  public function __construct(
    EntityManagerInterface $em,
    ValidatorInterface $validator,
    private readonly InventoryCategoryRepository $inventoryCategoryRepository,
    private readonly InventoryItemRepository $inventoryItemRepository,
  ) {
    parent::__construct($em, $validator);
  }

  #[\Override]
  protected function getRequiredCols(): array {
    return [
      self::COL_NAME,
      self::COL_SELLING_PRICE,
    ];
  }

  protected function callbackAfterRowsParsed(): void {
    $this->em->flush(); // Memory optimisation
  }

  protected function callbackEveryNParsedRows(): void {
    $this->em->flush();
  }

  protected function addItem(array &$row): AbstractImportedItemResult {
    $name = $this->getCurrentRowValue(self::COL_NAME);
    $existingItem = $this->inventoryItemRepository->findOneByName($this->getClub(), $name);
    if ($existingItem) {
      $warning = new WarningImportedItem($name);
      $warning->addWarning(self::ERROR_CODES[200]);
      return $warning; // We return it since we stop the import for it
    }

    $categoryName = $this->getCurrentRowValue(self::COL_CATEGORY_NAME);
    $category = $this->inventoryCategoryRepository->findOneByName($this->getClub(), $categoryName);
    if (!$category) { // We create the category
      if (array_key_exists($categoryName, $this->createdCategories)) {
        $category = $this->createdCategories[$categoryName];
      } else {
        $category = new InventoryCategory();
        $category
          ->setClub($this->getClub())
          ->setName($categoryName);
        $this->createdCategories[$categoryName] = $category;
        $this->em->persist($category);
      }
    }

    $inventoryItem = new InventoryItem();
    $inventoryItem
      ->setClub($this->getClub())
      ->setName($name)
      ->setDescription($this->getCurrentRowValue(self::COL_DESCRIPTION))
      ->setBarcode($this->getCurrentRowValue(self::COL_BARCODE))
      ->setSellingPrice($this->getCurrentRowValue(self::COL_SELLING_PRICE))
      ->setSellingQuantity($this->sanitizeColValueForNumber($this->getCurrentRowValue(self::COL_SELLING_QUANTITY)))
      ->setPurchasePrice($this->getCurrentRowValue(self::COL_PURCHASE_PRICE))
      ->setCanBeSold($this->toBoolean($this->getCurrentRowValue(self::COL_CAN_BE_SOLD)))
      ->setQuantity($this->sanitizeColValueForNumber($this->getCurrentRowValue(self::COL_QUANTITY)))
      ->setQuantityAlert($this->sanitizeColValueForNumber($this->getCurrentRowValue(self::COL_QUANTITY_ALERT)))
      ->setCategory($category);

    $this->em->persist($inventoryItem);

    return new SuccessImportedItem([
      "uuid" => $inventoryItem->getUuid()
    ]);
  }

  public function getClub(): Club {
    return $this->club;
  }

  public function setClub(Club $club): ImportInventoryItem {
    $this->club = $club;
    return $this;
  }
}
