<?php

namespace App\Service\Plugin\TimeAndTravelDeclaration;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\File;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportAttestation;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Enum\FileCategory;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Service\FileService;
use App\Service\ZipService;
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
    private readonly ZipService $zipService,
    private readonly MileageRateCalculationService $mileageRateCalculationService,
    private readonly MemberRepository $memberRepository,
  ) {
  }

  public function generate(TimeAndTravelExport $export): void {
    $export->setSmicHourlyRate($export->getClub()?->getSettings()?->getSmicHourlyRate());

    $this->build($export);
  }

  /**
   * Rebuilds a draft export. With $memberUuids, only those members' attestations are regenerated
   * (their declarations re-attached, totals recomputed) — the recap and the zip always are, since
   * they span every member. Without, or when the export was never fully generated, everything is.
   *
   * @param string[] $memberUuids
   */
  public function regenerate(TimeAndTravelExport $export, array $memberUuids = []): void {
    $members = $this->resolveMembers($memberUuids);
    if ($members === null || !$export->getRecapFile()) {
      $this->reset($export);
      $this->generate($export);
      return;
    }

    $this->reset($export, $members);
    $this->build($export, $members);
  }

  /**
   * Detaches declarations and discards the generated files, for the given members only (all when empty).
   * The recap and the zip are always discarded.
   *
   * @param Member[] $members
   */
  private function reset(TimeAndTravelExport $export, array $members = []): void {
    foreach ($this->declarationRepository->findByExport($export, $members) as $declaration) {
      $declaration->setExport(null);
    }

    $memberIds = array_flip(array_map(static fn (Member $member) => $member->getId(), $members));
    foreach ($export->getAttestations() as $attestation) {
      if ($members && !isset($memberIds[$attestation->getMember()?->getId()])) {
        continue;
      }
      $this->discardFile($attestation->getFile());
      $export->getAttestations()->removeElement($attestation);
      $this->entityManager->remove($attestation);
    }

    $this->discardFile($export->getRecapFile());
    $export->setRecapFile(null);

    $this->discardFile($export->getZipFile());
    $export->setZipFile(null);

    $this->entityManager->flush();
  }

  /**
   * Attaches the exportable declarations (of the given members only, all when empty), generates their
   * attestations, then the recap and the zip. Attestations are priced with the export's SMIC snapshot.
   *
   * @param Member[] $members
   */
  private function build(TimeAndTravelExport $export, array $members = []): void {
    $smicRate = $export->getSmicHourlyRate();
    $smicRateFloat = $smicRate !== null ? (float) $smicRate : 0.0;

    $byMember = [];
    foreach ($this->declarationRepository->findExportableForPeriod($export->getClub(), $export->getStartDate(), $export->getEndDate(), $members) as $declaration) {
      $declaration->setExport($export);
      $member = $declaration->getMember();
      if (!$member) {
        continue;
      }
      $byMember[$member->getId()][] = $declaration;
    }

    uasort($byMember, fn (array $a, array $b) => strcmp((string) $a[0]->getMember()?->getFullName(), (string) $b[0]->getMember()?->getFullName()));

    foreach ($byMember as $memberDeclarations) {
      $this->generateAttestation($export, $memberDeclarations[0]->getMember(), $memberDeclarations, $smicRateFloat);
    }

    // The recap's per-member declaration counts come from the database
    $this->entityManager->flush();

    $this->generateRecapAndZip($export);

    $this->entityManager->flush();
  }

  /**
   * @param string[] $memberUuids
   * @return Member[]|null Null when the whole export must be regenerated: no member given, or one no longer exists
   */
  private function resolveMembers(array $memberUuids): ?array {
    if (!$memberUuids) {
      return null;
    }

    $memberUuids = array_unique($memberUuids);
    $members = $this->memberRepository->findBy(['uuid' => $memberUuids]);

    return count($members) === count($memberUuids) ? $members : null;
  }

  /**
   * Builds one member's attestation (totals + PDF) from their declarations and adds it to the export.
   *
   * @param TimeAndTravelDeclaration[] $memberDeclarations
   */
  private function generateAttestation(TimeAndTravelExport $export, Member $member, array $memberDeclarations, float $smicRate): void {
    $club = $export->getClub();
    $totals = $this->computeTotals($memberDeclarations, $smicRate);
    $formattedTotals = $this->formatTotalsForTemplate($totals);

    $attestation = new TimeAndTravelExportAttestation();
    $attestation
      ->setMember($member)
      ->setTotalKilometers($totals['totalKilometers'])
      ->setTotalHours($formattedTotals['totalHours'])
      ->setTotalTravelAmount($formattedTotals['totalTravelAmount'])
      ->setTotalTimeAmount($formattedTotals['totalTimeAmount']);

    $pdfBytes = $this->pdfService->renderAttestation($export, $member, $formattedTotals);
    $file = $this->persistPdf($pdfBytes, $this->slugFilename('attestation', $member->getFullName() ?? $member->getUuid()->toString(), $export), FileCategory::time_and_travel_attestation, $club);
    $attestation->setFile($file);

    $export->addAttestation($attestation);
    $this->entityManager->persist($attestation);
  }

  /**
   * Renders the recap from the export's attestations (their stored totals — so members whose PDF was
   * not regenerated are still counted) and bundles it with every attestation in the zip.
   */
  private function generateRecapAndZip(TimeAndTravelExport $export): void {
    $club = $export->getClub();

    $declarationCounts = [];
    foreach ($this->declarationRepository->findByExport($export) as $declaration) {
      $memberId = $declaration->getMember()?->getId();
      $declarationCounts[$memberId] = ($declarationCounts[$memberId] ?? 0) + 1;
    }

    $attestations = $export->getAttestations()->toArray();
    usort($attestations, fn (TimeAndTravelExportAttestation $a, TimeAndTravelExportAttestation $b) => strcmp((string) $a->getMember()?->getFullName(), (string) $b->getMember()?->getFullName()));

    $recapRows = [];
    $grandTotals = ['declarationCount' => 0, 'totalKilometers' => 0, 'totalHours' => 0.0, 'totalTravelAmount' => 0.0, 'totalTimeAmount' => 0.0, 'totalAmount' => 0.0];
    $attestationFiles = [];

    foreach ($attestations as $attestation) {
      $member = $attestation->getMember();
      $declarationCount = $declarationCounts[$member?->getId()] ?? 0;

      $recapRows[] = [
        'memberName' => $member?->getFullName(),
        'memberLicence' => $member?->getLicence(),
        'declarationCount' => $declarationCount,
        'totalKilometers' => $attestation->getTotalKilometers(),
        'totalHours' => $attestation->getTotalHours(),
        'totalTravelAmount' => $attestation->getTotalTravelAmount(),
        'totalTimeAmount' => $attestation->getTotalTimeAmount(),
        'totalAmount' => number_format($attestation->getTotalAmount(), 2, '.', ''),
      ];

      $grandTotals['declarationCount'] += $declarationCount;
      $grandTotals['totalKilometers'] += $attestation->getTotalKilometers();
      $grandTotals['totalHours'] += (float) $attestation->getTotalHours();
      $grandTotals['totalTravelAmount'] += (float) $attestation->getTotalTravelAmount();
      $grandTotals['totalTimeAmount'] += (float) $attestation->getTotalTimeAmount();
      $grandTotals['totalAmount'] += $attestation->getTotalAmount();

      if ($attestation->getFile()) {
        $attestationFiles[] = $attestation->getFile();
      }
    }

    $recapBytes = $this->pdfService->renderRecap($export, $recapRows, $this->formatTotalsForTemplate($grandTotals));
    $recapFile = $this->persistPdf($recapBytes, $this->slugFilename('recapitulatif', $club?->getName() ?? 'club', $export), FileCategory::time_and_travel_recap, $club);
    $export->setRecapFile($recapFile);

    $zipFile = $this->zipService->createZip(
      [$recapFile, ...$attestationFiles],
      $this->slugFilename('export', $club?->getName() ?? 'club', $export, 'zip'),
      FileCategory::time_and_travel_export_bundle,
      club: $club,
      flush: false,
    );
    $export->setZipFile($zipFile);
  }

  private function discardFile(?File $file): void {
    if (!$file) {
      return;
    }

    $this->fileService->remove($file);
    $this->entityManager->remove($file);
  }

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

  private function slugFilename(string $prefix, string $name, TimeAndTravelExport $export, string $extension = 'pdf'): string {
    $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name));
    $period = $export->getStartDate()?->format('Ymd') . '-' . $export->getEndDate()?->format('Ymd');
    return "{$prefix}-{$slug}-{$period}.{$extension}";
  }
}
