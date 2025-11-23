<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Repository\ClubDependent\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ImportEdenService {

  public function __construct(
    private readonly EntityManagerInterface $em,
    private readonly ClubService $clubService,
    private readonly MemberRepository $memberRepository,
  ) {
  }

  /**
   * @param string $filename
   * @return int
   *
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
   */
  public function importFromFile(Club $club, string $filename): int {
    $reader = new Xlsx();
    $spreadsheet = $reader->load($filename);
    $rows = $spreadsheet->getSheet(0)->toArray();

    $header = $rows[0];
    unset($rows[0]);

    $colLicence = 0;
    $colExpirationDate = 0;

    foreach ($header as $k => $headerCol) {
      if ($headerCol === 'N° licence') {
        $colLicence = $k;
        continue;
      }

      if ($headerCol === 'Date d\'expiration') {
        $colExpirationDate = $k;
        continue;
      }
    }


    foreach ($rows as $row) {
      $this->importRow($club, $row, $colLicence, $colExpirationDate);
    }

    $this->em->flush();

    return count($rows);
  }

  private function importRow(Club $club, array $row, int $licenceIdx, int $dateIdx): void {
    $licence = $row[$licenceIdx];
    $medicalCert = $row[$dateIdx];

    if (empty($licence) || empty($medicalCert)) {
      return;
    }

    $member = $this->memberRepository->findOneByLicence($club, $licence);
    if (!$member) {
      return;
    }

    try {
      $date = new \DateTimeImmutable($medicalCert);
    } catch (\Exception) {
      return;
    }

    $member->setMedicalCertificateExpiration($date);
    $this->em->persist($member);
  }

}
