<?php

namespace App\MessageHandler;

use App\Entity\AgeCategory;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\MemberSeason;
use App\Entity\Season;
use App\Enum\ItacCsvHeaderMapping;
use App\Message\ItacMembersMessage;
use App\Repository\AgeCategoryRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\MemberSeasonRepository;
use App\Repository\ClubRepository;
use App\Repository\SeasonRepository;
use App\Service\SeasonService;
use App\Service\UtilsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ResetInterface;

#[AsMessageHandler]
class ItacMembersMessageHandler implements ResetInterface {
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
  ) {
  }

  public function reset(): void {
    $this->members = [];
    $this->seasons = [];
    $this->ageCategories = [];
  }


  public function __invoke(ItacMembersMessage $message): void {
//    $response = [
//      'failed' => 0,
//      'success' => 0
//    ];

    $club = $this->clubRepository->findOneByUuid($message->getClubUuid());

    foreach ($message->getRecords() as $record) {
      // We get the user, if he exists

      /** @var Member|null $member */
      $member = null;
      if (array_key_exists($record[ItacCsvHeaderMapping::LICENCE->value], $this->members)) {
        $member = $this->members[$record[ItacCsvHeaderMapping::LICENCE->value]];
      } else {
        /** @var Member|null $member */
        $member = $this->memberRepository->findOneByLicence($club, $record[ItacCsvHeaderMapping::LICENCE->value]);

        if (!$member) {
          $member = new Member();
          $member->setClub($club);
          $member->setLicence($record[ItacCsvHeaderMapping::LICENCE->value]);
        }
        $this->members[$record[ItacCsvHeaderMapping::LICENCE->value]] = $member;
      }


      if (!empty($record[ItacCsvHeaderMapping::PHONE->value])) {
        $member->setPhone(str_pad(str_replace(" ", "", $record[ItacCsvHeaderMapping::PHONE->value]), 10, "0", STR_PAD_LEFT));
      }

      if (!empty($record[ItacCsvHeaderMapping::MOBILE_PHONE->value])) {
        $member->setMobilePhone(str_pad(str_replace(" ", "", $record[ItacCsvHeaderMapping::MOBILE_PHONE->value]), 10, "0", STR_PAD_LEFT));
      }

      $email = $record[ItacCsvHeaderMapping::EMAIL->value];
      if (!empty($email)) {
        $member->setEmail($email);
      }

      $member
        ->setGender($record[ItacCsvHeaderMapping::GENDER->value])
        ->setLastname($record[ItacCsvHeaderMapping::LASTNAME->value])
        ->setFirstname($record[ItacCsvHeaderMapping::FIRSTNAME->value])
        ->setBirthday(\DateTime::createFromFormat("d/m/Y", $record[ItacCsvHeaderMapping::BIRTHDAY->value]))
        //->setHandisport($this->toBoolean($record[ItacCsvHeaderMapping::HANDISPORT->value]))

        ->setPostal1($record[ItacCsvHeaderMapping::POSTAL_1->value])
        ->setPostal2($record[ItacCsvHeaderMapping::POSTAL_2->value])
        ->setPostal3($record[ItacCsvHeaderMapping::POSTAL_3->value])
        ->setCity($record[ItacCsvHeaderMapping::CITY->value])
        ->setCountry($record[ItacCsvHeaderMapping::COUNTRY->value])

        ->setBlacklisted($record[ItacCsvHeaderMapping::BLACKLISTED->value] !== 'Autorisé');

      if (is_numeric($record[ItacCsvHeaderMapping::ZIP_CODE->value])) {
        $member
          ->setZipCode(intval($record[ItacCsvHeaderMapping::ZIP_CODE->value]));
      }

      $errors = $this->validator->validate($member);
      if (count($errors) > 0) {
        // TODO: Will be use when Message will contain a batch id so we can have more detailed logs in db
//        $response['failed'] += 1;
//        $response['failed_messages'][] = [
//          'licence' => $member->getLicence(),
//          'email' => $member->getEmail(),
//          'lastname' => $member->getLastname(),
//          'firstname' => $member->getFirstname(),
//          'message' => (string) $errors
//        ];
        continue;
      }

      // TODO: Will be use when Message will contain a batch id so we can have more detailed logs in db
      // $response['success'] += 1;
      // We persist it
      $this->entityManager->persist($member);
      // We set his seasons
      $this->defineMemberSeason($member, $record);
    }

    $this->entityManager->flush();
//    dump($response);
  }

  private function toBoolean($value): bool {
    return UtilsService::toBoolean($value);
  }

  private function defineMemberSeason(Member $member, array $record): void {
    $memberSeason = new MemberSeason();
    $memberSeason->setMember($member);

    $seasonCsv = $record[ItacCsvHeaderMapping::SEASON->value];
    // We check if already in our array
    /** @var Season|null $season */
    $season = null;
    if (array_key_exists($seasonCsv, $this->seasons)) {
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

    $ageCodeCsv = $record[ItacCsvHeaderMapping::AGE_CODE->value];
    // We check if already in our array
    /** @var AgeCategory|null $ageCategory */
    $ageCategory = null;
    if (array_key_exists($ageCodeCsv, $this->ageCategories)) {
      $ageCategory = $this->ageCategories[$ageCodeCsv];
    } else {
      // We get it in the db
      $ageCategoryDb = $this->ageCategoryRepository->findOneByCode($ageCodeCsv);
      if ($ageCategoryDb) {
        $this->ageCategories[$ageCodeCsv] = $ageCategoryDb;
        $ageCategory = $this->ageCategories[$ageCodeCsv];
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

    // If this memberSeason already exist (ignore ageCategory) we don't create it
    $msDb = $this->memberSeasonRepository->findOneByMemberAndSeason($memberSeason->getMember(), $memberSeason->getSeason());
    if ($msDb) {
      return;
    }

    $this->entityManager->persist($memberSeason);
  }
}
