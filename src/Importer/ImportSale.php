<?php

namespace App\Importer;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Sale\Sale;
use App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem;
use App\Importer\Model\AbstractImportedItemResult;
use App\Importer\Model\ErrorImportedItem;
use App\Importer\Model\SuccessImportedItem;
use App\Importer\Model\WarningImportedItem;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\Plugin\Sale\SalePaymentModeRepository;
use App\Repository\ClubDependent\Plugin\Sale\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportSale extends AbstractCsvImporter {
  private Club $club;

  public const string COL_SALE_ID = 'uuid';
  public const string COL_DATE = 'createdAt';
  public const string COL_PRICE = 'price';
  public const string COL_COMMENT = 'comment';

  public const string  COL_SELLER = 'seller.licence';

  public const string COL_PAYMENT_MODE = 'paymentMode.name';

  public const string COL_ITEMS = 'item';
  public const string COL_ITEM_NAME = 'name';
  public const string COL_ITEM_CATEGORY = 'category';
  public const string COL_ITEM_PRICE = 'price';
  public const string COL_ITEM_QUANTITY = 'quantity';


  public const array ERROR_CODES = [
    // 1xx: Error
    100 => ["errorCode" => "member-not-found", "reason" => "Member not found"],
    101 => ["errorCode" => "date-wrong-format", "reason" => "Date wrongly formatted"],
    102 => ["errorCode" => "payment-mode-not-found", "reason" => "Payment mode not found"],

    // 2xx: Warning
    200 => ["errorCode" => "sale-already-registered", "reason" => "Sale already registered. Safely ignoring it."],

  ];

  public function __construct(
    EntityManagerInterface $em,
    ValidatorInterface $validator,
    private readonly MemberRepository $memberRepository,
    private readonly SalePaymentModeRepository $paymentModeRepository,
    private readonly SaleRepository $saleRepository,
  ) {
    parent::__construct($em, $validator);
  }

  #[\Override]
  protected function getRequiredCols(): array {
    return [
      self::COL_DATE,
      self::COL_SELLER,
      self::COL_PAYMENT_MODE
    ];
  }

  protected function callbackAfterRowsParsed(): void {
    $this->em->flush(); // Memory optimisation
  }

  protected function callbackEveryNParsedRows(): void {
    $this->em->flush();
  }

  protected function addItem(array &$row): AbstractImportedItemResult {
    $licence = $this->getCurrentRowValue(self::COL_SELLER);
    if (empty($licence)) return new ErrorImportedItem(data: self::ERROR_CODES[100]);

    $date = $this->getCurrentRowValue(self::COL_DATE);
    if (!$date) return new ErrorImportedItem($licence, self::ERROR_CODES[101]);
    $date = new \DateTimeImmutable($date);

    $member = $this->memberRepository->findOneByLicence($this->getClub(), $licence);
    if (!$member) return new ErrorImportedItem($licence, self::ERROR_CODES[100]);

    $paymentMode = $this->paymentModeRepository->findOneByName($this->getClub(), $this->getCurrentRowValue(self::COL_PAYMENT_MODE));
    if (empty($paymentMode)) return new ErrorImportedItem($licence, self::ERROR_CODES[102]);

    $id = $this->getCurrentRowValue(self::COL_SALE_ID);
    $price = $this->getCurrentRowValue(self::COL_PRICE);
    $comment = $this->getCurrentRowValue(self::COL_COMMENT);

    // We check the sale is not already registered
    if (!empty($id)) {
      $existingSale = $this->saleRepository->findOneByClubAndUuid($this->getClub(), $id);
      if ($existingSale) {
        $warning = new WarningImportedItem($id);
        $warning->addWarning(self::ERROR_CODES[200]);
        return $warning; // We return it since we stop the import for it
      }
    }

    $sale = new Sale();
    $sale
      ->setClub($this->getClub())
      ->setSeller($member)
      ->setPrice($price)
      ->setComment($comment)
      ->setPaymentMode($paymentMode)
      ->setCreatedAt($date)
      ->setUpdatedAt($date);

    $this->callbackForRowMultiCol(self::COL_ITEMS, function($v) use ($sale) {
      foreach ($v as $value) {
        if (!array_key_exists(self::COL_ITEM_NAME, $value) || empty($value[self::COL_ITEM_NAME])) continue;
        if (!array_key_exists(self::COL_ITEM_PRICE, $value) || empty($value[self::COL_ITEM_PRICE])) continue;
        if (!array_key_exists(self::COL_ITEM_QUANTITY, $value) || empty($value[self::COL_ITEM_QUANTITY])) continue;

        $purchasedItem = new SalePurchasedItem();
        $purchasedItem
          ->setItemName($value[self::COL_ITEM_NAME])
          ->setItemPrice($value[self::COL_ITEM_PRICE])
          ->setQuantity($value[self::COL_ITEM_QUANTITY]);

        if (array_key_exists(self::COL_ITEM_CATEGORY, $value) && !empty($value[self::COL_ITEM_CATEGORY])) {
          $purchasedItem->setItemCategory($value[self::COL_ITEM_CATEGORY]);
        }

        $this->em->persist($purchasedItem);
        $sale->addSalePurchasedItem($purchasedItem);
      }
    });

    $this->em->persist($sale);

    return new SuccessImportedItem([
      "uuid" => $sale->getUuid()
    ]);
  }

  public function getClub(): Club {
    return $this->club;
  }

  public function setClub(Club $club): ImportSale {
    $this->club = $club;
    return $this;
  }
}
