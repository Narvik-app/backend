<?php

namespace App\Service;

use App\Entity\Club;
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
    $this->clubService->setItacImport($club, count($recordsChunks));

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
    $this->clubService->setItacSecondaryImport($club, count($recordsChunks));

    foreach ($recordsChunks as $recordsChunk) {
      $chunk = [];
      foreach ($recordsChunk as $key => $value) {
        foreach ($value as $k => $v) {
          $chunk[$key][$this->convert($k)] = $this->convert($v);
        }
      }
      $this->bus->dispatch(new ItacSecondaryClubMembersMessage($club->getUuid(), $chunk));
    }

    return count($array);
  }
}
