<?php

namespace App\Service;

use App\Entity\Club;
use App\Enum\ClubJobKey;
use App\Message\ItacMembersMessage;
use App\Message\ItacSecondaryClubMembersMessage;
use League\Csv\Reader;
use Symfony\Component\Messenger\MessageBusInterface;

class ImportItacCsvService extends AbstractCsvService {

  public function __construct(
    private readonly MessageBusInterface $bus,
    private readonly ClubService $clubService,
  ) {
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
    $this->clubService->startJob($club, ClubJobKey::itac_import, count($recordsChunks));

    foreach ($recordsChunks as $recordsChunk) {
      $chunk = [];
      foreach ($recordsChunk as $key => $value) {
        foreach ($value as $k => $v) {
          $chunk[$key][$this->convert($k)] = $this->convert($v);
        }
      }
      $this->bus->dispatch(new ItacMembersMessage($club->getUuid()->toString(), $chunk));
    }

    return count($array);
  }

  /**
   * @param string $filename
   * @return int
   * @throws \League\Csv\Exception
   * @throws \League\Csv\UnavailableStream
   */
  public function importSecondaryFromFile(Club $club, string $filename): int {
    $reader = Reader::from($filename);
    $reader->setHeaderOffset(0); // Header is in first line
    $records = $reader->getRecords();
    $array = iterator_to_array($records);
    $recordsChunks = array_chunk($array, 100);
    $this->clubService->startJob($club, ClubJobKey::itac_secondary_import, count($recordsChunks));

    foreach ($recordsChunks as $recordsChunk) {
      $chunk = [];
      foreach ($recordsChunk as $key => $value) {
        foreach ($value as $k => $v) {
          $chunk[$key][$this->convert($k)] = $this->convert($v);
        }
      }
      // Was passing $club->getUuid() (a Uuid object) here instead of ->toString() like every
      // other dispatch site — ItacSecondaryClubMembersMessage's ctor types it as string, so this
      // only worked by (Stringable) coercion; made explicit for consistency.
      $this->bus->dispatch(new ItacSecondaryClubMembersMessage($club->getUuid()->toString(), $chunk));
    }

    return count($array);
  }
}
