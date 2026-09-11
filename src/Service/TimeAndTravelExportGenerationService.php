<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\File;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportAttestation;
use App\Enum\FileCategory;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File as SfFile;

/**
 * Generates (and regenerates) a draft TimeAndTravelExport: attaches the
 * exportable declarations for its period, groups them per member, renders
 * both PDF kinds and persists everything through FileService.
 */
class TimeAndTravelExportGenerationService {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly TimeAndTravelDeclarationRepository $declarationRepository,
    private readonly TimeAndTravelPdfService $pdfService,
    private readonly FileService $fileService,
  ) {
  }

  public function generate(TimeAndTravelExport $export): void {
    $club = $export->getClub();
    $smicRate = $club?->getSettings()?->getSmicHourlyRate();
    $export->setSmicHourlyRate($smicRate);

    $declarations = $this->declarationRepository->findExportableForPeriod($club, $export->getStartDate(), $export->getEndDate());

    /** @var array<int, TimeAndTravelDeclaration[]> $byMember */
    $byMember = [];
    foreach ($declarations as $declaration) {
      $declaration->setExport($export);
      $member = $declaration->getMember();
      if (!$member) {
        continue;
      }
      $byMember[$member->getId()][] = $declaration;
    }

    $smicRateFloat = $smicRate !== null ? (float) $smicRate : 0.0;
    $recapRows = [];
    $grandTotals = ['declarationCount' => 0, 'totalKilometers' => 0, 'totalHours' => 0.0, 'totalTravelAmount' => 0.0, 'totalTimeAmount' => 0.0, 'totalAmount' => 0.0];

    foreach ($byMember as $memberDeclarations) {
      $member = $memberDeclarations[0]->getMember();
      $totals = $this->computeTotals($memberDeclarations, $smicRateFloat);

      $attestation = new TimeAndTravelExportAttestation();
      $attestation
        ->setMember($member)
        ->setTotalKilometers($totals['totalKilometers'])
        ->setTotalHours(number_format($totals['totalHours'], 2, '.', ''))
        ->setTotalTravelAmount(number_format($totals['totalTravelAmount'], 2, '.', ''))
        ->setTotalTimeAmount(number_format($totals['totalTimeAmount'], 2, '.', ''))
        ->setTotalAmount(number_format($totals['totalAmount'], 2, '.', ''));

      $pdfBytes = $this->pdfService->renderAttestation($export, $member, $memberDeclarations, $this->formatTotalsForTemplate($totals));
      $file = $this->persistPdf($pdfBytes, $this->slugFilename('attestation', $member->getFullName() ?? $member->getUuid()->toString(), $export), FileCategory::time_and_travel_attestation, $club);
      $attestation->setFile($file);

      $export->addAttestation($attestation);
      $this->entityManager->persist($attestation);

      $recapRows[] = [
        'memberName' => $member->getFullName(),
        'memberLicence' => $member->getLicence(),
        'declarationCount' => count($memberDeclarations),
        'totalKilometers' => $totals['totalKilometers'],
        'totalHours' => number_format($totals['totalHours'], 2, '.', ''),
        'totalTravelAmount' => number_format($totals['totalTravelAmount'], 2, '.', ''),
        'totalTimeAmount' => number_format($totals['totalTimeAmount'], 2, '.', ''),
        'totalAmount' => number_format($totals['totalAmount'], 2, '.', ''),
      ];

      $grandTotals['declarationCount'] += count($memberDeclarations);
      $grandTotals['totalKilometers'] += $totals['totalKilometers'];
      $grandTotals['totalHours'] += $totals['totalHours'];
      $grandTotals['totalTravelAmount'] += $totals['totalTravelAmount'];
      $grandTotals['totalTimeAmount'] += $totals['totalTimeAmount'];
      $grandTotals['totalAmount'] += $totals['totalAmount'];
    }

    $recapBytes = $this->pdfService->renderRecap($export, $recapRows, $this->formatTotalsForTemplate($grandTotals));
    $recapFile = $this->persistPdf($recapBytes, $this->slugFilename('recapitulatif', $club?->getName() ?? 'club', $export), FileCategory::time_and_travel_recap, $club);
    $export->setRecapFile($recapFile);

    $this->entityManager->flush();
  }

  /**
   * Detaches every declaration and deletes the previously generated files,
   * then regenerates from scratch. Only valid while the export is a draft
   * (enforced by the caller).
   */
  public function regenerate(TimeAndTravelExport $export): void {
    foreach ($this->declarationRepository->findByExport($export) as $declaration) {
      $declaration->setExport(null);
    }

    if ($export->getRecapFile()) {
      $this->fileService->remove($export->getRecapFile());
      $this->entityManager->remove($export->getRecapFile());
      $export->setRecapFile(null);
    }

    foreach ($export->getAttestations() as $attestation) {
      if ($attestation->getFile()) {
        $this->fileService->remove($attestation->getFile());
        $this->entityManager->remove($attestation->getFile());
      }
      $export->getAttestations()->removeElement($attestation);
      $this->entityManager->remove($attestation);
    }

    $this->entityManager->flush();

    $this->generate($export);
  }

  /**
   * @param TimeAndTravelDeclaration[] $declarations
   * @return array{totalKilometers: int, totalHours: float, totalTravelAmount: float, totalTimeAmount: float, totalAmount: float}
   */
  private function computeTotals(array $declarations, float $smicRate): array {
    $totalKilometers = 0;
    $totalHours = 0.0;
    $totalTravelAmount = 0.0;

    foreach ($declarations as $declaration) {
      $totalKilometers += $declaration->getKilometers() ?? 0;
      $totalHours += (float) ($declaration->getHours() ?? 0);
      $totalTravelAmount += $declaration->getTravelAmount();
    }

    $totalTimeAmount = $totalHours * $smicRate;

    return [
      'totalKilometers' => $totalKilometers,
      'totalHours' => $totalHours,
      'totalTravelAmount' => $totalTravelAmount,
      'totalTimeAmount' => $totalTimeAmount,
      'totalAmount' => $totalTravelAmount + $totalTimeAmount,
    ];
  }

  /**
   * @param array{totalKilometers: int, totalHours: float, totalTravelAmount: float, totalTimeAmount: float, totalAmount: float} $totals
   */
  private function formatTotalsForTemplate(array $totals): array {
    return [
      'declarationCount' => $totals['declarationCount'] ?? null,
      'totalKilometers' => $totals['totalKilometers'],
      'totalHours' => number_format($totals['totalHours'], 2, '.', ''),
      'totalTravelAmount' => number_format($totals['totalTravelAmount'], 2, '.', ''),
      'totalTimeAmount' => number_format($totals['totalTimeAmount'], 2, '.', ''),
      'totalAmount' => number_format($totals['totalAmount'], 2, '.', ''),
    ];
  }

  private function persistPdf(string $bytes, string $filename, FileCategory $category, ?Club $club): File {
    $tmpPath = tempnam(sys_get_temp_dir(), 'ttpdf_') . '.pdf';
    file_put_contents($tmpPath, $bytes);

    $sfFile = new SfFile($tmpPath);

    return $this->fileService->importFile($sfFile, $filename, $category, isPublic: false, club: $club, flush: false);
  }

  private function slugFilename(string $prefix, string $name, TimeAndTravelExport $export): string {
    $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name));
    $period = $export->getStartDate()?->format('Ymd') . '-' . $export->getEndDate()?->format('Ymd');
    return "{$prefix}-{$slug}-{$period}.pdf";
  }
}
