<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\File;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportAttestation;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
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
    private readonly MileageRateCalculationService $mileageRateCalculationService,
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

    // Alphabetical by lastname then firstname (Member::getFullName() is already "LASTNAME Firstname"),
    // so the recap and the attestations are generated/listed in the same order.
    uasort($byMember, fn (array $a, array $b) => strcmp((string) $a[0]->getMember()?->getFullName(), (string) $b[0]->getMember()?->getFullName()));

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

      $pdfBytes = $this->pdfService->renderAttestation($export, $member, $this->formatTotalsForTemplate($totals));
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
   * The official mileage scale prices a vehicle's WHOLE cumulative distance for the period under
   * a single bracket (not marginally per km), so travel amount can't be summed declaration by
   * declaration for a vehicle using the official scale — it has to be computed once per vehicle,
   * over that vehicle's total kilometers across every declaration in the period.
   *
   * @param TimeAndTravelDeclaration[] $declarations
   * @return array{totalKilometers: int, totalHours: float, totalTravelAmount: float, totalTimeAmount: float, totalAmount: float, vehicleBreakdown: array, kilometerDeclarations: TimeAndTravelDeclaration[], timeDeclarations: TimeAndTravelDeclaration[], kilometerDeclarationsHours: float, timeOnlyHours: float}
   */
  private function computeTotals(array $declarations, float $smicRate): array {
    $totalKilometers = 0;
    $totalHours = 0.0;

    // Split for display purposes: a kilometer-based trip vs a pure time declaration are shown in
    // separate tables in the attestation — the state doesn't need to see volunteer hours that
    // carry no kilometric reimbursement alongside the km trips.
    $kilometerDeclarations = [];
    $timeDeclarations = [];
    $kilometerDeclarationsHours = 0.0;
    $timeOnlyHours = 0.0;

    /** @var array<int, array{vehicle: MemberVehicle, kilometers: int}> $byVehicle */
    $byVehicle = [];

    foreach ($declarations as $declaration) {
      $kilometers = $declaration->getKilometers() ?? 0;
      $hours = (float) ($declaration->getHours() ?? 0);
      $totalKilometers += $kilometers;
      $totalHours += $hours;

      if ($kilometers > 0) {
        $kilometerDeclarations[] = $declaration;
        $kilometerDeclarationsHours += $hours;
      } else {
        $timeDeclarations[] = $declaration;
        $timeOnlyHours += $hours;
      }

      $vehicle = $declaration->getMemberVehicle();
      if (!$vehicle || $kilometers <= 0) {
        continue;
      }

      $byVehicle[$vehicle->getId()]['vehicle'] ??= $vehicle;
      $byVehicle[$vehicle->getId()]['kilometers'] = ($byVehicle[$vehicle->getId()]['kilometers'] ?? 0) + $kilometers;
    }

    $totalTravelAmount = 0.0;
    $vehicleBreakdown = [];
    foreach ($byVehicle as $entry) {
      $vehicle = $entry['vehicle'];
      $kilometers = $entry['kilometers'];

      $result = $this->mileageRateCalculationService->calculate($vehicle->getCategory(), (int) $vehicle->getFiscalPower(), $kilometers, $vehicle->isElectric());
      $totalTravelAmount += $result?->amount ?? 0.0;
      $vehicleBreakdown[] = [
        'vehicle' => $vehicle,
        'kilometers' => $kilometers,
        'description' => $result
          ? sprintf(
            '%s km × %s%s%s',
            $kilometers,
            $result->rate->getRate(),
            (float) $result->rate->getAddend() > 0 ? ' + ' . $result->rate->getAddend() . ' €' : '',
            $result->electricBonusRate > 0 ? sprintf(' (+%d%% véhicule électrique)', round($result->electricBonusRate * 100)) : ''
          )
          : 'Aucun barème applicable pour cette puissance/catégorie',
        'amount' => number_format($result?->amount ?? 0.0, 2, '.', ''),
      ];
    }

    $totalTimeAmount = $totalHours * $smicRate;

    return [
      'totalKilometers' => $totalKilometers,
      'totalHours' => $totalHours,
      'totalTravelAmount' => $totalTravelAmount,
      'totalTimeAmount' => $totalTimeAmount,
      'totalAmount' => $totalTravelAmount + $totalTimeAmount,
      'vehicleBreakdown' => $vehicleBreakdown,
      'kilometerDeclarations' => $kilometerDeclarations,
      'timeDeclarations' => $timeDeclarations,
      'kilometerDeclarationsHours' => $kilometerDeclarationsHours,
      'timeOnlyHours' => $timeOnlyHours,
    ];
  }

  /**
   * @param array{totalKilometers: int, totalHours: float, totalTravelAmount: float, totalTimeAmount: float, totalAmount: float, vehicleBreakdown?: array, kilometerDeclarations?: TimeAndTravelDeclaration[], timeDeclarations?: TimeAndTravelDeclaration[], kilometerDeclarationsHours?: float, timeOnlyHours?: float} $totals
   */
  private function formatTotalsForTemplate(array $totals): array {
    return [
      'declarationCount' => $totals['declarationCount'] ?? null,
      'totalKilometers' => $totals['totalKilometers'],
      'totalHours' => number_format($totals['totalHours'], 2, '.', ''),
      'totalTravelAmount' => number_format($totals['totalTravelAmount'], 2, '.', ''),
      'totalTimeAmount' => number_format($totals['totalTimeAmount'], 2, '.', ''),
      'totalAmount' => number_format($totals['totalAmount'], 2, '.', ''),
      'vehicleBreakdown' => $totals['vehicleBreakdown'] ?? [],
      'kilometerDeclarations' => $totals['kilometerDeclarations'] ?? [],
      'timeDeclarations' => $totals['timeDeclarations'] ?? [],
      'kilometerDeclarationsHours' => number_format($totals['kilometerDeclarationsHours'] ?? 0.0, 2, '.', ''),
      'timeOnlyHours' => number_format($totals['timeOnlyHours'] ?? 0.0, 2, '.', ''),
      'electricBonusRatePercent' => (int) round($this->mileageRateCalculationService->getElectricBonusRate() * 100),
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
