<?php

namespace App\EventSubscriber\Doctrine;

use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItemHistory;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;

#[AsEntityListener(entity: InventoryItem::class)]
class InventoryItemSubscriber extends AbstractEventSubscriber {
  public function __construct() {
  }

  public function postUpdate(InventoryItem $inventoryItem, PostUpdateEventArgs $args): void {
    $objectManager = $args->getObjectManager();
    if (!$this->hasChangedProperties($objectManager, $inventoryItem, ['purchasePrice', 'sellingPrice', 'quantity'])) {
      return;
    }

    $itemHistory = new InventoryItemHistory();
    $itemHistory
      ->setPurchasePrice($inventoryItem->getPurchasePrice())
      ->setSellingPrice($inventoryItem->getSellingPrice())
      ->setQuantity($inventoryItem->getQuantity())
      ->setItem($inventoryItem)
      ->setSale($inventoryItem->getProcessingSale());

    $objectManager->persist($itemHistory);
    $objectManager->flush();
  }
}
