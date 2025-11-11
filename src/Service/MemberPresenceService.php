<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Presence\ExternalPresence;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Message\ItacMembersMessage;
use App\Message\MemberPresencesCsvMessage;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\Plugin\Presence\ExternalPresenceRepository;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Messenger\MessageBusInterface;

class MemberPresenceService extends AbstractCsvService {
  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly EntityManagerInterface $em,
    private readonly ExternalPresenceRepository $externalPresenceRepository,
    private readonly MemberRepository $memberRepository,
    private readonly MemberPresenceRepository $memberPresenceRepository,
  ) {
  }
  public function importFromExternalPresence(Club $club): int {
    $totalImported = 0;

    $presencesWithLicences = $this->externalPresenceRepository->findAllWithLicence($club);
    /** @var ExternalPresence $extPresence */
    foreach ($presencesWithLicences as $extPresence) {
      $member = $this->memberRepository->findOneByLicence($club, $extPresence->getLicence());
      if (!$member) continue;

      // We check we don't have any record of it already
      $alreadyPresent = $this->memberPresenceRepository->findOneByDay($member, $extPresence->getDate());
      if ($alreadyPresent) {
        $this->em->remove($extPresence);
        continue;
      };

      // We create the presence
      $presence = new MemberPresence();
      $presence
        ->setMember($member)
        ->setDate($extPresence->getDate())
        ->setCreatedAt($extPresence->getCreatedAt());

      foreach ($extPresence->getActivities() as $activity) {
        $presence->addActivity($activity);
      }

      $this->em->persist($presence);
      $totalImported++;

      // We delete the old external presence
      $this->em->remove($extPresence);
    }

    $this->em->flush();

    return $totalImported;
  }

  /**
   * @param string $filename
   * @return int
   * @throws \League\Csv\Exception
   * @throws \League\Csv\UnavailableStream
   */
  public function importFromFile(Club $club, string $filename): int {
    $reader = Reader::from($filename);
    $reader->setHeaderOffset(0); // Header is in first line
    $records = $reader->getRecords();
    $array = iterator_to_array($records);
    $recordsChunks = array_chunk($array, 100);

    foreach ($recordsChunks as $recordsChunk) {
      $chunk = [];
      foreach ($recordsChunk as $key => $value) {
        foreach ($value as $k => $v) {
          $chunk[$key][$this->convert($k)] = $this->convert($v);
        }
      }
      $this->bus->dispatch(new MemberPresencesCsvMessage($club->getUuid()->toString(), $chunk));
    }

    return count($array);
  }
}
