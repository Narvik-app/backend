<?php

namespace App\Importer;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Presence\ExternalPresence;
use App\Importer\Model\AbstractImportedItemResult;
use App\Importer\Model\ErrorImportedItem;
use App\Importer\Model\SuccessImportedItem;
use App\Importer\Model\WarningImportedItem;
use App\Repository\ClubDependent\ActivityRepository;
use App\Repository\ClubDependent\Plugin\Presence\ExternalPresenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportExternalPresence extends AbstractCsvImporter {
  private Club $club;

  public const string COL_LICENCE = 'licence';
  public const string COL_FIRSTNAME = 'firstname';
  public const string COL_LASTNAME = 'lastname';
  public const string COL_DATE = 'date';
  public const string  COL_ACTIVITIES = 'activities';
  public const string  COL_CREATED_AT = 'createdAt';

  public const array ERROR_CODES = [
    // 1xx: Error
    101 => ["errorCode" => "date-wrong-format", "reason" => "Date wrongly formatted"],

    // 2xx: Warning
    200 => ["errorCode" => "presence-already-registered", "reason" => "Member presence already registered. Safely ignoring it."],

  ];

  public function __construct(
    EntityManagerInterface $em,
    ValidatorInterface $validator,
    private readonly ActivityRepository $activityRepository,
    private readonly ExternalPresenceRepository $externalPresenceRepository,
  ) {
    parent::__construct($em, $validator);
  }

  protected function getRequiredCols(): array {
    return [
      self::COL_FIRSTNAME,
      self::COL_LASTNAME,
      self::COL_DATE
    ];
  }

  protected function callbackAfterRowsParsed(): void {
    $this->em->flush(); // Memory optimisation
  }

  protected function callbackEveryNParsedRows(): void {
    $this->em->flush();
  }

  protected function addItem(array &$row): AbstractImportedItemResult {
    $licence = $this->getCurrentRowValue(self::COL_LICENCE);
    $firstname = $this->getCurrentRowValue(self::COL_FIRSTNAME);
    $lastname = $this->getCurrentRowValue(self::COL_LASTNAME);

    $date = $this->getCurrentRowValue(self::COL_DATE);
    if (!$date) return new ErrorImportedItem($licence, self::ERROR_CODES[101]);

    $date = new \DateTimeImmutable($date);

    // We check the presence is not already registered
    $existingPresence = $this->externalPresenceRepository->findOneByDay($this->getClub(), $firstname, $lastname, $date);
    if ($existingPresence) {
      $warning = new WarningImportedItem("$lastname $firstname");
      $warning->addWarning(self::ERROR_CODES[200]);
      return $warning; // We return it since we stop the import for it
    }


    $externalPresence = new ExternalPresence();

    $createdAt = $this->getCurrentRowValue(self::COL_CREATED_AT);
    if ($createdAt) {
      try {
        $createdAt = new \DateTimeImmutable($createdAt);
        $externalPresence->setCreatedAt($createdAt);
        $date = $date->setTime($createdAt->format('H'), $createdAt->format('i'), $createdAt->format('s'));
      } catch (\Exception $e) {
      }
    }

    $externalPresence
      ->setClub($this->getClub())
      ->setLicence($licence)
      ->setFirstName($firstname)
      ->setLastName($lastname)
      ->setDate($date);

    $this->callbackForRowMultiCol(self::COL_ACTIVITIES, function($v) use ($externalPresence) {
      foreach ($v as $value) {
        if (!array_key_exists("name", $value) || empty($value["name"])) continue;
        $activityName = $value["name"];
        $activity = $this->activityRepository->findOneByName($this->getClub(), $activityName);

        if (!$activity) continue; // We don't create activity, we just ignore

        $externalPresence->addActivity($activity);
      }
    });

    $this->em->persist($externalPresence);

    return new SuccessImportedItem([
      "uuid" => $externalPresence->getUuid()
    ]);
  }

  public function getClub(): Club {
    return $this->club;
  }

  public function setClub(Club $club): ImportExternalPresence {
    $this->club = $club;
    return $this;
  }
}
