<?php

namespace App\MessageHandler;

use App\Entity\Club;
use App\Entity\ClubDependent\Activity;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\ExternalPresence;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Message\CerberePresencesDateMessage;
use App\Repository\ClubDependent\ActivityRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\Plugin\Presence\ExternalPresenceRepository;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ResetInterface;

#[AsMessageHandler]
class CerberePresencesDateMessageHandler implements ResetInterface {
  /** @var Activity[] */
  private array $activities = [];
  /** @var Member[]  */
  private array $members = [];

  private array $externalMembers = [];

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly ClubRepository $clubRepository,
    private readonly MemberRepository $memberRepository,
    private readonly ExternalPresenceRepository $externalPresenceRepository,
    private readonly ActivityRepository $activityRepository,
    private readonly ValidatorInterface $validator,
  ) {
  }

  public function reset(): void {
    $this->members = [];
    $this->activities = [];
    $this->externalMembers = [];
  }

  public function __invoke(CerberePresencesDateMessage $message): void {
    $club = $this->clubRepository->findOneByUuid($message->getClubUuid());

    $this->generateActivitiesList($club);
    $this->generateMembersList($club);
    $this->generateExternalPresenceList($club);

    $date = $message->getDate();
    foreach ($message->getDatas() as $data) {
      $licence = $data['licence'];
      $activities = $data['activities'];

      if (!array_key_exists((string) $licence, $this->members)) {
        $this->registerExternalPresence($club, $licence, $data['fullName'], $activities, $date);
        continue;
      }

      $member = $this->members[$licence];

      // We verify the presence is not already saved
      foreach ($member->getMemberPresences() as $dbMemberPresence) {
        if ($dbMemberPresence->getDate()->format("Y-m-d") === $date->format("Y-m-d")) {
          continue 2; // 2 so we break both foreach loop and go to next presence
        }
      }

      $memberPresence = new MemberPresence();
      $memberPresence
        ->setClub($club)
        ->setMember($member)
        ->setDate($date);

      foreach ($activities as $activity) {
        $memberPresence->addActivity($this->activities[$activity]);
      }

      $this->entityManager->persist($memberPresence);
    }

    $this->entityManager->flush();
  }

  private function generateMembersList(Club $club): void {
    $this->members = [];
    foreach ($this->memberRepository->findAllByClub($club) as $dbMember) {
      $this->members[$dbMember->getLicence()] = $dbMember;
    }
  }

  private function generateExternalPresenceList(Club $club): void {
    $this->externalMembers = [];
    foreach ($this->externalPresenceRepository->findAllByClub($club) as $dbExtPresence) {
      if ($dbExtPresence->getLicence() && $dbExtPresence->getDate()) {
        $this->externalMembers[$dbExtPresence->getLicence()][] = $dbExtPresence->getDate()->format("Y-m-d");
      }
    }
  }

  private function generateActivitiesList(Club $club): void {
    $this->activities = [];
    foreach ($this->activityRepository->findAllByClub($club) as $activity) {
      $this->activities[$activity->getName()] = $activity;
    }
  }

  private function registerExternalPresence(Club $club, string $licence, string $fullName, array $activities, \DateTimeImmutable $date): void {
    // We verify the presence is not already saved
    $presenceAlreadyRegistered = false;
    if (array_key_exists($licence, $this->externalMembers)) {
      foreach ($this->externalMembers[$licence] as $presenceDate) {
        if ($presenceDate === $date->format("Y-m-d")) {
          return; // We stop the registration
        }
      }
    }

    $this->externalMembers[$licence][] = $date->format("Y-m-d");

    $fullName = explode(' ', $fullName, 2);

    $externalPresence = new ExternalPresence();
    $externalPresence
      ->setClub($club)
      ->setLicence($licence)
      ->setLastname($fullName[0])
      ->setFirstname($fullName[1])
      ->setDate($date);


    foreach ($activities as $activity) {
      $externalPresence->addActivity($this->activities[$activity]);
    }

    $this->entityManager->persist($externalPresence);
  }
}
