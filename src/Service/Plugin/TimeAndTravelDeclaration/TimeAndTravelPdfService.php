<?php

namespace App\Service\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Service\FileService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class TimeAndTravelPdfService {
  /** @var array<string, ?string> */
  private array $logoBase64Cache = [];

  public function __construct(
    private readonly Environment $twig,
    private readonly FileService $fileService,
  ) {
  }

  /**
   * Renders the club-wide recap PDF for the comptable.
   *
   * @param array<int, array{memberName: string, memberLicence: ?string, declarationCount: int, totalKilometers: int, totalHours: string, totalTravelAmount: string, totalTimeAmount: string, totalAmount: string}> $rows
   * @param array{declarationCount: int, totalKilometers: int, totalHours: string, totalTravelAmount: string, totalTimeAmount: string, totalAmount: string} $totals
   */
  public function renderRecap(TimeAndTravelExport $export, array $rows, array $totals): string {
    $html = $this->twig->render('pdf/time-and-travel/recap.html.twig', [
      'club' => $export->getClub(),
      'logoBase64' => $this->getClubLogoBase64($export),
      'export' => $export,
      'rows' => $rows,
      'totals' => $totals,
    ]);

    return $this->renderPdf($html);
  }

  /**
   * Renders one member's attestation PDF.
   *
   * @param TimeAndTravelDeclaration[] $declarations
   * @param array{totalKilometers: int, totalHours: string, totalTravelAmount: string, totalTimeAmount: string, totalAmount: string} $totals
   */
  public function renderAttestation(TimeAndTravelExport $export, Member $member, array $totals): string {
    $html = $this->twig->render('pdf/time-and-travel/attestation.html.twig', [
      'club' => $export->getClub(),
      'logoBase64' => $this->getClubLogoBase64($export),
      'export' => $export,
      'member' => $member,
      'totals' => $totals,
    ]);

    return $this->renderPdf($html);
  }

  private function renderPdf(string $html): string {
    $options = new Options();
    $options->set('isRemoteEnabled', false); // We embed the logo as a data URI instead, no outbound HTTP from the renderer
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
  }

  private function getClubLogoBase64(TimeAndTravelExport $export): ?string {
    $logo = $export->getClub()?->getSettings()?->getLogo();
    if (!$logo || !$logo->getUuid()) {
      return null;
    }

    $cacheKey = $logo->getUuid()->toString();
    if (!array_key_exists($cacheKey, $this->logoBase64Cache)) {
      $this->logoBase64Cache[$cacheKey] = $this->fileService->getFileDataUri($logo);
    }

    return $this->logoBase64Cache[$cacheKey];
  }
}
