<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\Sale\SalePurchasedItem;
use App\Enum\SalePaymentModeKind;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(entity: SalePurchasedItem::class)]
class SalePurchasedItemSubscriber extends AbstractEventSubscriber {
  public function __construct() {
  }

  public function prePersist(SalePurchasedItem $salePurchasedItem, PrePersistEventArgs $args): void {
    $this->autoSetFields($salePurchasedItem, $args);
  }

  public function preUpdate(SalePurchasedItem $salePurchasedItem, PreUpdateEventArgs $args): void {
    $this->autoSetFields($salePurchasedItem, $args);
  }

  public function autoSetFields(SalePurchasedItem $salePurchasedItem, LifecycleEventArgs $args): void {
    $item = $salePurchasedItem->getItem();
    if (!$item) {
      return;
    }

    if ($salePurchasedItem->getSale()?->getPaymentMode()?->getKind() === SalePaymentModeKind::stock_removal) {
      $salePurchasedItem->setItemPrice('0');
    } elseif (!$salePurchasedItem->getItemPrice()) {
      $salePurchasedItem->setItemPrice($item->getSellingPrice());
    }

    // We always update the itemName && category
    $salePurchasedItem
      ->setItemName($item->getName())
      ->setItemCategory($item->getCategory()?->getName());
  }
}
