<?php

namespace App\DataFixtures;

use App\Tests\Factory\ClubDependent\Plugin\Emailing\EmailFactory;
use App\Tests\Factory\ExternalPresenceFactory;
use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Entity\ClubDependent\Plugin\Loan\LoanRecordingType;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Tests\Factory\InventoryItemFactory;
use App\Tests\Factory\InventoryItemHistoryFactory;
use App\Tests\Factory\LoanCategoryFactory;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\LoanItemFactory;
use App\Tests\Factory\LoanRecordingFactory;
use App\Tests\Factory\LoanRecordingTypeFactory;
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

    /*******************************************************
     *                       LOAN                          *
     ******************************************************/

    $loanRecordingTypes = [
      LoanRecordingTypeFactory::createOne(['name' => 'Nettoyage', 'color' => '#22c55e']),
      LoanRecordingTypeFactory::createOne(['name' => 'Révision', 'color' => '#3b82f6']),
      LoanRecordingTypeFactory::createOne(['name' => 'Panne', 'color' => '#ef4444']),
      LoanRecordingTypeFactory::createOne(['name' => 'Contrôle', 'color' => '#f59e0b']),
    ];

    $loanItemsMapping = [
      'Optique' => ['Lunette 4x32', 'Point rouge'],
      'Armes' => ['Carabine 22LR', 'Pistolet 9mm', 'Arc classique'],
      'Accessoires' => ['Sac de transport', 'Trépied', 'Chronographe'],
    ];

    $weight = 0;
    $loanCategories = [];
    foreach ($loanItemsMapping as $categoryName => $itemNames) {
      $category = LoanCategoryFactory::createOne(['name' => $categoryName, 'weight' => $weight++]);
      $loanCategories[$categoryName] = $category;
      foreach ($itemNames as $name) {
        $item = LoanItemFactory::createOne(['name' => $name, 'category' => $category]);
        $this->generateLoanHistory($item, $loanRecordingTypes);
      }
    }

    LoanItemFactory::createOne([
      'name' => 'Carabine de prêt',
      'category' => $loanCategories['Armes'],
      'loanPrice' => 15.00,
      'visibleOnSalePage' => true,
    ]);
    LoanItemFactory::createOne([
      'name' => 'Pistolet de prêt',
      'category' => $loanCategories['Armes'],
      'loanPrice' => 10.00,
      'visibleOnSalePage' => true,
    ]);
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

  /**
   * Generates a few years of non-overlapping loan history (and the occasional maintenance
   * recording) for a single LoanItem, so the loan statistics page has real quantity to show.
   *
   * @param LoanRecordingType[] $recordingTypes
   */
  private function generateLoanHistory(LoanItem $item, array $recordingTypes, int $months = 30): void {
    $date = new \DateTimeImmutable("-{$months} months");
    $now = new \DateTimeImmutable();
    $lastRecordingDate = $date;

    while ($date < $now) {
      // Idle time between loans
      $date = $date->modify('+' . faker()->numberBetween(8, 25) . ' days');
      if ($date >= $now) {
        break;
      }

      $startDate = $date;
      $durationDays = faker()->numberBetween(1, 7);
      $endDate = $startDate->modify("+{$durationDays} days");

      // Leave a few recent loans currently open, to simulate items out in the field
      $isRecent = $startDate >= $now->modify('-10 days');
      $stillOpen = $isRecent && faker()->boolean(50);

      $member = faker()->boolean(75) ? MemberFactory::random() : null;
      LoanFactory::createOne([
        'loanItem' => $item,
        'startDate' => $startDate,
        'endDate' => $stillOpen ? null : ($endDate < $now ? $endDate : $now),
        'member' => $member,
        'borrowerName' => $member === null && faker()->boolean(50) ? faker()->name() : null,
      ]);

      $date = $stillOpen ? $now : $endDate;

      // Occasional maintenance/cleaning recording between loans
      if ($date >= $lastRecordingDate->modify('+45 days') && faker()->boolean(35)) {
        LoanRecordingFactory::createOne([
          'loanItem' => $item,
          'recordingType' => faker()->randomElement($recordingTypes),
          'date' => $date,
        ]);
        $lastRecordingDate = $date;
      }
    }
  }
}
