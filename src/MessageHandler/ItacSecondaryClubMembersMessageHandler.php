<?php

namespace App\MessageHandler;

use App\Entity\AgeCategory;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberSeason;
use App\Entity\Season;
use App\Enum\ItacSecondaryClubCsvHeaderMapping;
use App\Message\ItacSecondaryClubMembersMessage;
use App\Repository\AgeCategoryRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\MemberSeasonRepository;
use App\Repository\ClubRepository;
use App\Repository\SeasonRepository;
use App\Service\MemberPresenceService;
use App\Service\SeasonService;
use App\Service\UtilsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ResetInterface;

#[AsMessageHandler]
class ItacSecondaryClubMembersMessageHandler implements ResetInterface {
  private array $members = [];
  private array $seasons = [];
  private array $ageCategories = [];

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly ClubRepository $clubRepository,
    private readonly MemberRepository $memberRepository,
    private readonly SeasonRepository $seasonRepository,
    private readonly AgeCategoryRepository $ageCategoryRepository,
    private readonly MemberSeasonRepository $memberSeasonRepository,
    private readonly ValidatorInterface $validator,
    private readonly MemberPresenceService $memberPresenceService,
  ) {
  }

  public function reset(): void {
    $this->members = [];
    $this->seasons = [];
    $this->ageCategories = [];
  }


  public function __invoke(ItacSecondaryClubMembersMessage $message): void {
    $club = $this->clubRepository->findOneByUuid($message->getClubUuid());

    foreach ($message->getRecords() as $record) {
      // We get the user, if he exists

      /** @var Member|null $member */
      $member = null;
      if (array_key_exists((string) $record[ItacSecondaryClubCsvHeaderMapping::LICENCE->value], $this->members)) {
        $member = $this->members[$record[ItacSecondaryClubCsvHeaderMapping::LICENCE->value]];
      } else {
        /** @var Member|null $member */
        $member = $this->memberRepository->findOneByLicence($club, $record[ItacSecondaryClubCsvHeaderMapping::LICENCE->value]);

        if (!$member) {
          $member = new Member();
          $member->setClub($club);
          $member->setLicence($record[ItacSecondaryClubCsvHeaderMapping::LICENCE->value]);
        }
        $this->members[$record[ItacSecondaryClubCsvHeaderMapping::LICENCE->value]] = $member;
      }


      if (!empty($record[ItacSecondaryClubCsvHeaderMapping::PHONE->value])) {
        $member->setPhone(str_pad(str_replace(" ", "", $record[ItacSecondaryClubCsvHeaderMapping::PHONE->value]), 10, "0", STR_PAD_LEFT));
      }

      if (!empty($record[ItacSecondaryClubCsvHeaderMapping::MOBILE_PHONE->value])) {
        $member->setMobilePhone(str_pad(str_replace(" ", "", $record[ItacSecondaryClubCsvHeaderMapping::MOBILE_PHONE->value]), 10, "0", STR_PAD_LEFT));
      }

      $email = $record[ItacSecondaryClubCsvHeaderMapping::EMAIL->value];
      if (!empty($email)) {
        $member->setEmail($email);
      }

      $member
        ->setGender($record[ItacSecondaryClubCsvHeaderMapping::GENDER->value])
        ->setLastname($record[ItacSecondaryClubCsvHeaderMapping::LASTNAME->value])
        ->setFirstname($record[ItacSecondaryClubCsvHeaderMapping::FIRSTNAME->value])

        ->setPostal1($record[ItacSecondaryClubCsvHeaderMapping::POSTAL_1->value])
        ->setPostal2($record[ItacSecondaryClubCsvHeaderMapping::POSTAL_2->value])
        ->setPostal3($record[ItacSecondaryClubCsvHeaderMapping::POSTAL_3->value])
        ->setZipCode($record[ItacSecondaryClubCsvHeaderMapping::ZIP_CODE->value])
        ->setCity($record[ItacSecondaryClubCsvHeaderMapping::CITY->value]);

      $errors = $this->validator->validate($member);
      if (count($errors) > 0) {
        continue;
      }
      // We persist it
      $this->entityManager->persist($member);
      // We set his seasons
      $this->defineMemberSeason($member, $record);
    }

    $this->entityManager->flush();

    // We run the migration
    $this->memberPresenceService->importFromExternalPresence($club);
  }

  private function toBoolean($value): bool {
    return UtilsService::toBoolean($value);
  }

  private function defineMemberSeason(Member $member, array $record): void {
    $memberSeason = new MemberSeason();
    $memberSeason->setMember($member);

    $seasonCsv = $record[ItacSecondaryClubCsvHeaderMapping::SEASON->value];
    // We check if already in our array
    /** @var Season|null $season */
    $season = null;
    if (array_key_exists((string) $seasonCsv, $this->seasons)) {
      $season = $this->seasons[$seasonCsv];
    } else {
      // We get it in the db
      $seasonDb = $this->seasonRepository->findOneByName($seasonCsv);
      if ($seasonDb) {
        $this->seasons[$seasonCsv] = $seasonDb;
        $season = $this->seasons[$seasonCsv];
      } else {
        // We create it
        $season = $this->seasonRepository->findOrCreateOneByName($seasonCsv, false);
        if (!$season) {
          return;
        }
        $this->seasons[$seasonCsv] = $season;
      }
    }

    if ($season->getId()) {
      $ref = $this->entityManager->getReference(Season::class, $season->getId());
      if ($ref) {
        $season = $ref;
      }
    }
    $memberSeason->setSeason($season);

    $ageCodeNameCsv = $record[ItacSecondaryClubCsvHeaderMapping::AGE_CATEGORY->value];
    // We check if already in our array
    /** @var AgeCategory|null $ageCategory */
    $ageCategory = null;
    if (array_key_exists((string) $ageCodeNameCsv, $this->ageCategories)) {
      $ageCategory = $this->ageCategories[$ageCodeNameCsv];
    } else {
      // We get it in the db
      $ageCategoryDb = $this->ageCategoryRepository->findOneByCode($ageCodeNameCsv);
      if ($ageCategoryDb) {
        $this->ageCategories[$ageCodeNameCsv] = $ageCategoryDb;
        $ageCategory = $this->ageCategories[$ageCodeNameCsv];
      }
      // Otherwise, no mapping with age category, we don't create (ageCategories are managed globally not at a club level)
    }

    if ($ageCategory) {
      if ($ageCategory->getId()) {
        $ref = $this->entityManager->getReference(AgeCategory::class, $ageCategory->getId());
        if ($ref) {
          $ageCategory = $ref;
        }
      }
      $memberSeason->setAgeCategory($ageCategory);
    }


    $memberSeason->setIsSecondaryClub(true);

    // If this memberSeason already exist (ignore ageCategory) we don't create it
    $msDb = $this->memberSeasonRepository->findOneByMemberAndSeason($memberSeason->getMember(), $memberSeason->getSeason());
    if ($msDb) {
      return;
    }

    $this->entityManager->persist($memberSeason);
  }
}
