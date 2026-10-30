<?php

namespace App\DataFixtures;

use App\Tests\Factory\ClubDependent\Plugin\Emailing\EmailFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportFactory;
use App\Tests\Factory\ExternalPresenceFactory;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Entity\ClubDependent\Plugin\Loan\LoanRecordingType;
use App\Entity\ClubDependent\Plugin\Sale\InventoryItem;
use App\Tests\Factory\ActivityFactory;
use App\Tests\Factory\InventoryItemFactory;
use App\Tests\Factory\InventoryItemHistoryFactory;
use App\Tests\Factory\LoanCategoryFactory;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\LoanItemFactory;
use App\Tests\Factory\LoanRecordingFactory;
use App\Tests\Factory\LoanRecordingTypeFactory;
use App\Tests\Factory\MemberControlFactory;
use App\Tests\Factory\MemberControlTypeFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\SaleFactory;
use App\Tests\Factory\MemberPresenceFactory;
use App\Tests\Factory\MemberSeasonFactory;
use App\Enum\TimeAndTravelExportStatus;
use App\Service\ImageService;
use App\Service\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportGenerationService;
use App\Tests\FixtureFileManager;
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
  public function __construct(
    private readonly ImageService $imageService,
    private readonly TimeAndTravelExportGenerationService $exportGenerationService,
  ) {
  }

  public function load(ObjectManager $manager): void {
    // Same data on every load, so screenshots (documentation / about website) stay stable
    faker()->seed(20260101);

    // We create the bare minium required (some users and clubs)
    _InitStory::load();

    MemberFactory::new()->many(250)->create();
    $this->importDemoImages();

    // We create the default global settings
    GlobalSettingStory::load();

    // We record some presence
    MemberPresenceFactory::new()->many(20)->create();
    ExternalPresenceFactory::new()->many(25)->create();
    // The demo admin has a presence history, for the member page (details and charts)
    // (never on the activity feeding the yearly control, which has to stay overdue for the demo)
    $adminActivities = array_values(array_filter(
      ActivityFactory::findBy(['club' => _InitStory::club_1()]),
      static fn ($activity) => $activity->getName() !== 'Contrôle annuel',
    ));
    MemberPresenceFactory::new()->many(14)->create(fn () => [
      'member' => _InitStory::MEMBER_admin_club_1(),
      'activities' => faker()->randomElements($adminActivities, faker()->numberBetween(1, 3)),
    ]);

    $this->generateMemberControls();

    EmailFactory::new()->many(12)->create();

    /*******************************************************
     *                    INVENTORY                        *
     ******************************************************/

    // We create the default categories
    $defaultCategoriesPool = InventoryCategoryStory::load();

    $itemsMapping = [
      "Boutique" => ['T-shirt du club', 'Casquette', 'Gourde', 'Sweat à capuche'],
      "Buvette" => ['Café', 'Eau minérale', 'Jus de fruits', 'Barre chocolatée'],
      "Administratif" => ['Licence', 'Droit d\'entrée', 'Second club'],
      "Séances" => ['Séance à l\'unité', 'Carnet de 10 séances'],
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

    // Item name => demo picture (tests/fixtures/demo/loan-items)
    $loanItemsMapping = [
      'Événementiel' => ['Tente de réception' => 'tente', 'Tonnelle pliante' => 'tonnelle', 'Barbecue' => 'barbecue'],
      'Son et image' => ['Vidéoprojecteur' => 'projecteur', 'Enceinte sono' => 'sono'],
      'Loisirs' => ['Vélo' => 'velo', 'Table de pique-nique' => 'table', 'Jeux en bois' => 'jeux'],
    ];

    $weight = 0;
    $loanCategories = [];
    foreach ($loanItemsMapping as $categoryName => $items) {
      $category = LoanCategoryFactory::createOne(['name' => $categoryName, 'weight' => $weight++]);
      $loanCategories[$categoryName] = $category;
      foreach ($items as $name => $picture) {
        $item = LoanItemFactory::createOne(['name' => $name, 'category' => $category]);
        $this->imageService->importLoanItemImage($item, FixtureFileManager::getUploadedFile("demo/loan-items/$picture.jpg", tmpCopy: true, test: true));
        $this->generateLoanHistory($item, $loanRecordingTypes);
      }
    }

    $paidLoanItems = [
      ['Raquettes de badminton', 5.00, 'jeux'],
      ['Vélo électrique', 10.00, 'velo'],
    ];
    foreach ($paidLoanItems as [$name, $price, $picture]) {
      $item = LoanItemFactory::createOne([
        'name' => $name,
        'category' => $loanCategories['Loisirs'],
        'loanPrice' => $price,
        'visibleOnSalePage' => true,
      ]);
      $this->imageService->importLoanItemImage($item, FixtureFileManager::getUploadedFile("demo/loan-items/$picture.jpg", tmpCopy: true, test: true));
    }

    /*******************************************************
     *              TIME AND TRAVEL DECLARATION            *
     ******************************************************/

    // A handful of members get a vehicle and a history of declarations, so the boards
    // (admin and personal) have real data to show for club_1, on which the plugin is enabled.
    // The demo accounts (admin, supervisor) always have some, as they are the ones used for screenshots.
    $timeAndTravelMembers = [
      _InitStory::MEMBER_admin_club_1(),
      _InitStory::MEMBER_supervisor_club_1(),
      ...MemberFactory::randomRange(6, 10, ['club' => _InitStory::club_1()]),
    ];
    foreach ($timeAndTravelMembers as $member) {
      $vehicle = MemberVehicleFactory::createOne(['member' => $member]);
      TimeAndTravelDeclarationFactory::new()
        ->many(3)
        ->create(['member' => $member, 'memberVehicle' => $vehicle]);
    }

    // A couple of hours-only declarations (no distance, no vehicle needed)
    TimeAndTravelDeclarationFactory::new()->many(4)->create(fn () => [
      'member' => faker()->randomElement($timeAndTravelMembers),
      'departureLocation' => null,
      'arrivalLocation' => null,
      'kilometers' => null,
      'memberVehicle' => null,
      'isRoundtrip' => false,
    ]);

    $this->generateTimeAndTravelExports($manager, $timeAndTravelMembers);
  }

  /**
   * An old locked export (with its attestations and zip) and a draft one on the recent declarations.
   *
   * @param Member[] $members
   */
  private function generateTimeAndTravelExports(ObjectManager $manager, array $members): void {
    $club = _InitStory::club_1();
    $club->getSettings()?->setSmicHourlyRate('11.88');

    // Declarations of the demo accounts inside the locked period, so their "Attestations" section is filled
    foreach (array_slice($members, 0, 2) as $member) {
      $vehicle = MemberVehicleFactory::findBy(['member' => $member])[0] ?? null;
      foreach (['-6 weeks', '-7 weeks'] as $date) {
        TimeAndTravelDeclarationFactory::createOne(['member' => $member, 'memberVehicle' => $vehicle, 'date' => new \DateTimeImmutable($date)]);
      }
    }
    $manager->flush();

    $locked = TimeAndTravelExportFactory::createOne(['startDate' => new \DateTimeImmutable('-9 weeks'), 'endDate' => new \DateTimeImmutable('-5 weeks')]);
    $this->exportGenerationService->generate($locked);
    $locked->setStatus(TimeAndTravelExportStatus::locked)->setLockedAt(new \DateTimeImmutable('-4 weeks'))->setLockedBy(_InitStory::MEMBER_admin_club_1());

    $draft = TimeAndTravelExportFactory::createOne(['startDate' => new \DateTimeImmutable('-4 weeks'), 'endDate' => new \DateTimeImmutable('now')]);
    $this->exportGenerationService->generate($draft);

    $manager->flush();
  }

  /**
   * A yearly control (linked to an activity, with warning and alert delays) and a manual one without expiration,
   * both shown on the presence card. The demo admin has them, so do a few other members (some overdue).
   */
  private function generateMemberControls(): void {
    $club = _InitStory::club_1();
    $activity = ActivityFactory::findBy(['club' => $club, 'name' => 'Contrôle annuel'])[0] ?? null;

    $yearly = MemberControlTypeFactory::createOne([
      'name' => 'Contrôle annuel',
      'icon' => 'shield-check',
      'activity' => $activity,
      'warningDays' => 335,
      'alertDays' => 365,
      'displayOnPresenceCard' => true,
      'weight' => 0,
    ]);
    $quiz = MemberControlTypeFactory::createOne([
      'name' => 'QCM',
      'icon' => 'question-mark-circle',
      'activity' => null,
      'warningDays' => null,
      'alertDays' => null,
      'displayOnPresenceCard' => true,
      'weight' => 1,
    ]);

    $admin = _InitStory::MEMBER_admin_club_1();
    // The date of a control linked to an activity follows the presences on it: overdue ones need an old presence
    MemberPresenceFactory::createOne(['member' => $admin, 'activities' => [$activity], 'date' => new \DateTimeImmutable('-14 months')]);
    MemberControlFactory::createOne(['member' => $admin, 'type' => $quiz, 'date' => new \DateTimeImmutable('-2 months')]);

    foreach (MemberFactory::randomRange(20, 30, ['club' => $club]) as $member) {
      if ($member->getId() === $admin->getId()) {
        continue;
      }
      MemberPresenceFactory::createOne([
        'member' => $member,
        'activities' => [$activity],
        'date' => new \DateTimeImmutable('-' . faker()->numberBetween(30, 420) . ' days'),
      ]);
    }
  }

  /**
   * Generic pictures (no real people) for the club logo and the first members of the club.
   */
  private function importDemoImages(): void {
    $club = _InitStory::club_1();
    $this->imageService->importClubLogo($club, FixtureFileManager::getUploadedFile(FixtureFileManager::LOGO, tmpCopy: true, test: true));

    $members = MemberFactory::findBy(['club' => $club]);
    foreach (array_slice($members, 0, 12) as $index => $member) {
      $this->imageService->importMemberPhoto($member, FixtureFileManager::getUploadedFile(sprintf('demo/avatars/avatar-%02d.jpg', $index + 1), tmpCopy: true, test: true));
    }
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
