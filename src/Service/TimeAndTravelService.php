<?php

namespace App\Service;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\ClubDependent\ClubSetting;
use App\Repository\ClubDependent\ClubSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class TimeAndTravelService {
  private EntityManagerInterface $entityManager;
  private ClubSettingRepository $clubSettingRepository;
  public function __construct(
    EntityManagerInterface $entityManager,
    ClubSettingRepository $clubSettingRepository,
  ) {
    $this->entityManager = $entityManager;
    $this->clubSettingRepository = $clubSettingRepository;
  }

  /**
   * Calculate time value based on hours worked and SMIC rate
   */
  public function calculateTimeValue(TimeAndTravelDeclaration $declaration): float {
    $hours = (float) $declaration->getHours();
    $smicRate = $this->getSmicHourlyRate($declaration->getClub());

    return $hours * $smicRate;
  }

  /**
   * Get SMIC hourly rate from club settings
   */
  public function getSmicHourlyRate(?string $clubUuid): ?float {
    if (!$clubUuid) {
      return null;
    }

    $clubSetting = $this->clubSettingRepository->findOneBy(['clubUuid' => $clubUuid]);
    if (!$clubSetting || !$clubSetting->getSmicHourlyRate()) {
      return null;
    }

    return (float)$clubSetting->getSmicHourlyRate();
  }

  /**
   * Calculate monthly totals for a club and year
   */
  public function calculateMonthlyTotals(string $clubUuid, int $year): array {
    /** @var TimeAndTravelDeclaration[] $declarations */
    $declarations = $this->entityManager
      ->getRepository(TimeAndTravelDeclaration::class)
      ->findByDateRange(
        new \DateTimeImmutable("$year-01-01"),
        new \DateTimeImmutable("$year-12-31"),
        $clubUuid
      );

    $monthlyTotals = [];

    foreach ($declarations as $declaration) {
      $month = $declaration->getDate()->format('Y-m');

      if (!isset($monthlyTotals[$month])) {
        $monthlyTotals[$month] = [
          'month' => $month,
          'totalKilometers' => 0,
          'totalHours' => 0.0,
          'totalFiscalReduction' => 0.0,
          'totalTimeValue' => 0.0,
          'totalAmount' => 0.0,
          'declarationCount' => 0,
        ];
      }

      $monthlyTotals[$month]['totalKilometers'] += $declaration->getKilometers();
      $monthlyTotals[$month]['totalHours'] += (float) $declaration->getHours();
      $monthlyTotals[$month]['totalFiscalReduction'] += (float) ($declaration->getFiscalReduction() ?? 0);
      $monthlyTotals[$month]['declarationCount']++;
    }

    // Sort by month
    ksort($monthlyTotals);

    return array_values($monthlyTotals);
  }

  /**
   * Calculate yearly totals for a club and year
   */
  public function calculateYearlyTotals(string $clubUuid, int $year): array {
    $monthlyTotals = $this->calculateMonthlyTotals($clubUuid, $year);

    $yearlyTotals = [
      'year' => $year,
      'totalKilometers' => 0,
      'totalHours' => 0.0,
      'totalFiscalReduction' => 0.0,
      'totalTimeValue' => 0.0,
      'totalAmount' => 0.0,
      'declarationCount' => 0,
      'monthlyBreakdown' => $monthlyTotals,
    ];

    foreach ($monthlyTotals as $month) {
      $yearlyTotals['totalKilometers'] += $month['totalKilometers'];
      $yearlyTotals['totalHours'] += $month['totalHours'];
      $yearlyTotals['totalFiscalReduction'] += $month['totalFiscalReduction'];
      $yearlyTotals['totalTimeValue'] += $month['totalTimeValue'];
      $yearlyTotals['totalAmount'] += $month['totalAmount'];
      $yearlyTotals['declarationCount'] += $month['declarationCount'];
    }

    return $yearlyTotals;
  }

  /**
   * Calculate member totals for a club and year
   */
  public function calculateMemberTotals(string $clubUuid, int $year): array {
    /** @var TimeAndTravelDeclaration[] $declarations */
    $declarations = $this->entityManager
      ->getRepository(TimeAndTravelDeclaration::class)
      ->findByDateRange(
        new \DateTimeImmutable("$year-01-01"),
        new \DateTimeImmutable("$year-12-31"),
        $clubUuid
      );

    $memberTotals = [];

    foreach ($declarations as $declaration) {
      $member = $declaration->getMember();
      if (!$member) continue;

      $memberUuid = $member->getUuid();

      if (!isset($memberTotals[$memberUuid])) {
        $memberTotals[$memberUuid] = [
          'memberUuid' => $memberUuid,
          'memberName' => $member->getFullName(),
          'memberLicence' => $member->getLicence(),
          'totalKilometers' => 0,
          'totalHours' => 0.0,
          'totalFiscalReduction' => 0.0,
          'totalTimeValue' => 0.0,
          'totalAmount' => 0.0,
          'declarationCount' => 0,
        ];
      }

      $memberTotals[$memberUuid]['totalKilometers'] += $declaration->getKilometers();
      $memberTotals[$memberUuid]['totalHours'] += (float) $declaration->getHours();
      $memberTotals[$memberUuid]['totalFiscalReduction'] += (float) ($declaration->getFiscalReduction() ?? 0);
      $memberTotals[$memberUuid]['declarationCount']++;
    }

    // Sort by member name
    usort($memberTotals, function($a, $b) {
      return strcmp($a['memberName'], $b['memberName']);
    });

    return array_values($memberTotals);
  }
}
