<?php

namespace App\DataFixtures;

use App\Tests\Factory\ClubDependent\Plugin\Emailing\EmailFactory;
use App\Tests\Factory\ExternalPresenceFactory;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Tests\Factory\InventoryItemFactory;
use App\Tests\Factory\InventoryItemHistoryFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\SaleFactory;
use App\Tests\Factory\MemberPresenceFactory;
use App\Tests\Factory\MemberSeasonFactory;
use App\Tests\Story\_InitStory;
use App\Tests\Story\ActivityStory;
use App\Tests\Story\AgeCategoryStory;
use App\Tests\Story\GlobalSettingStory;
use App\Tests\Story\InventoryCategoryStory;
use App\Tests\Story\SalePaymentModeStory;
use App\Tests\Story\SeasonStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use function Zenstruck\Foundry\faker;

class AppFixtures extends Fixture {
  public function load(ObjectManager $manager): void {
    // We create the bare minium required (some users and clubs)
    _InitStory::load();

    MemberFactory::new()->many(250)->create();

    // We create the default global settings
    GlobalSettingStory::load();

    // We record some presence
    MemberPresenceFactory::new()->many(20)->create();
    ExternalPresenceFactory::new()->many(25)->create();

    EmailFactory::new()->many(12)->create();

    /*******************************************************
     *                    INVENTORY                        *
     ******************************************************/

    // We create the default categories
    $defaultCategoriesPool = InventoryCategoryStory::load();

    $itemsMapping = [
      "Cibles" => ['Cible C50', 'Visuel C50', 'Pistolet 10M', 'Carabine 10M'],
      "Munitions" => ['semi-auto 22lr', '9mm - SB', '9mm - G', 'Plombs'],
      "Administratif" => ['licence', 'droit d\'entrée', 'second club'],
      "Droit de tir" => ['10M', '25/50M'],
    ];
    $categories = $defaultCategoriesPool->getPool('default');
    foreach ($categories as $category) {
      $catName = $category->getName();
      if (array_key_exists((string) $catName, $itemsMapping)) {
        foreach ($itemsMapping[$catName] as $name) {
          $item = InventoryItemFactory::createOne(['name' => $name, 'category' => $category, 'quantity' => faker()->numberBetween(60, 200)]);
          $this->generateItemHistory($item);
        }
      } else {
        $items = InventoryItemFactory::new()->many(5)->create(['category' => $category]);
        foreach ($items as $item) {
          InventoryItemHistoryFactory::new()->many(6)->create(['item' => $item]);
        }
      }
    }

    SalePaymentModeStory::load();
    SaleFactory::createMany(faker()->numberBetween(15, 25));
    SaleFactory::createMany(faker()->numberBetween(3, 6), ['createdAt' => new \DateTimeImmutable('yesterday')]);
    SaleFactory::createMany(faker()->numberBetween(3, 6), ['createdAt' => new \DateTimeImmutable('today')]);
  }

  private function generateItemHistory(InventoryItem $item, int $months = 6): void {
    $purchase = (float) ($item->getPurchasePrice() ?? 5);
    $selling  = (float) ($item->getSellingPrice() ?? 15);
    $stock    = faker()->numberBetween(60, 200);
    $date     = new \DateTimeImmutable("-{$months} months");
    $now      = new \DateTimeImmutable();

    while ($date < $now) {
      $date = $date->modify('+' . faker()->numberBetween(3, 10) . ' days');
      if ($date >= $now) {
        break;
      }
      if (faker()->boolean(20)) {
        $purchase = max(1, $purchase + faker()->randomFloat(2, -1, 1.5));
      }
      if (faker()->boolean(25)) {
        $selling = max($purchase + 1, $selling + faker()->randomFloat(2, -1.5, 2));
      }
      $stock = faker()->boolean(30)
        ? $stock + faker()->numberBetween(20, 100)         // restock
        : max(0, $stock - faker()->numberBetween(1, 15));  // sale drain
      InventoryItemHistoryFactory::createOne([
        'item'          => $item,
        'createdAt'     => $date,
        'purchasePrice' => round($purchase, 2),
        'sellingPrice'  => round($selling, 2),
        'quantity'      => $stock,
      ]);
    }
  }
}
