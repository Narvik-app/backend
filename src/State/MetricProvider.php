<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\MetricPagination;
use App\Entity\Club;
use App\Entity\ClubDependent\Metric;
use App\Repository\ClubDependent\MemberControlTypeRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\MemberSeasonRepository;
use App\Repository\ClubDependent\Plugin\Loan\LoanRepository;
use App\Repository\ClubDependent\Plugin\Presence\ExternalPresenceRepository;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use App\Repository\ClubDependent\Plugin\Sale\SaleRepository;
use App\Repository\Interface\PresenceRepositoryInterface;
use App\Repository\SeasonRepository;
use App\Enum\Permission;
use App\Service\RequestService;
use App\Service\SeasonService;
use App\Service\UtilsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MetricProvider implements ProviderInterface {
  public const array METRICS = [
    "members",
    "presences",
    "external-presences",
    "opened-days",
    "member-presence-stats",
    "loans",
    "sales-stats",
    "sales-per-item-stats",
//    "import-batches",
//    "activities"
  ];

  // TODO: super admin only metrics (import-batches)

  private ?Club $club = null;

  /**
   * @var array{start: ?\DateTimeImmutable, end: ?\DateTimeImmutable}
   */
  private array $filterDates = [
    'start' => null,
    'end' => null,
  ];

  public function __construct(
    private readonly LoggerInterface $logger,
    private readonly RequestStack $requestStack,
    private readonly RequestService $requestService,

    private readonly MemberRepository $memberRepository,
    private readonly MemberSeasonRepository $memberSeasonRepository,
    private readonly MemberControlTypeRepository $memberControlTypeRepository,
    private readonly MemberPresenceRepository $memberPresenceRepository,
    private readonly ExternalPresenceRepository $externalPresenceRepository,
    private readonly SeasonRepository $seasonRepository,
    private readonly LoanRepository $loanRepository,
    private readonly SaleRepository $saleRepository,
    private readonly AuthorizationCheckerInterface $authorizationChecker,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    $this->club = null;
    $this->filterDates = ['start' => null, 'end' => null]; // Reset for frankenPHP long-live singleton
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return null;
    }

    if (str_starts_with((string) $operation->getName(), 'club_metric')) {
      $this->club = $this->requestService->getClubFromRequest($request);
    }

    $previousSeason = UtilsService::toBoolean($request->query->get('previous-season', false));
    if ($previousSeason) {
      $this->filterDates['end'] = SeasonService::getPreviousSeasonEndDate($this->club);
    } else {
      $this->filterDates['end'] = SeasonService::getCurrentSeasonEndDate($this->club);
    }

    $startFilter = $request->query->get('start');
    $endFilter = $request->query->get('end');
    if ($endFilter) {
      try {
        // We split so if a date is malformed, the default filteredDate won't be modified
        $endDate = new \DateTimeImmutable($endFilter);
        $this->filterDates['end'] = $endDate;

        if ($startFilter) {
          $startDate = new \DateTimeImmutable($startFilter);
          $this->filterDates['start'] = $startDate;
        }
      } catch (\Exception $e) {
        $this->logger->warning('Invalid date filter', ['start' => $startFilter, 'end' => $endFilter, 'error' => $e->getMessage()]);
        throw new BadRequestHttpException('Invalid date filter.', $e);
      }
    }

    if ($operation instanceof CollectionOperationInterface) {
      return $this->getAll();
    }

    return $this->getOne($uriVariables['name']);
  }

  private function getAll(): array {
    $metrics = [];
    foreach (self::METRICS as $metric) {
      $metric = $this->getOne($metric);
      if ($metric) {
        $metrics[] = $metric;
      }
    }
    return $metrics;
  }

  private function getOne(string $identifier): ?Metric {
    if (in_array($identifier, self::METRICS)) {
      $getter = "get" . str_replace("-", "", $identifier);
      if (method_exists($this, $getter)) {
        return $this->$getter($identifier);
      }
    }
    return null;
  }

  protected function getMembers(string $identifier): Metric {
    $total = $this->memberRepository->countTotalClubMembers($this->club);
    $previousSeason = $this->memberSeasonRepository->countTotalMembersForPreviousSeason($this->club);
    $currentSeason = $this->memberSeasonRepository->countTotalMembersForThisSeason($this->club);

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValue($total);
    $metric->setChildMetrics([
      new Metric()
        ->setClub($this->club)
        ->setName("previous-season")
        ->setValue($previousSeason),
      new Metric()
        ->setClub($this->club)
        ->setName("current-season")
        ->setValue($currentSeason),
    ]);
    return $metric;
  }

  protected function getPresences(string $identifier): Metric {
    return $this->generatePresenceMetrics($identifier, $this->memberPresenceRepository);
  }

  protected function getExternalPresences(string $identifier): Metric {
    return $this->generatePresenceMetrics($identifier, $this->externalPresenceRepository);
  }

  private function generatePresenceMetrics(string $identifier, PresenceRepositoryInterface $repository): Metric {
    $totalPresences = $repository->countTotalPresences($this->club, $this->filterDates['end'], $this->filterDates['start']);
    $statsPerActivitiesDays = $repository->getPresencesStatsPerActivitiesPerDayOfWeek($this->club, $this->filterDates['end'], $this->filterDates['start']);

    $metrics = [];
    foreach ($statsPerActivitiesDays as $activityName => $statsPerDays) {
      $metricsForActivity = new Metric()->setName($activityName);
      $total = 0;

      foreach ($statsPerDays as $dayOfWeek => $stats) {
        $total += $stats['total'];
        $metricsForActivity
          ->addChildMetric(
            new Metric()
              ->setName($dayOfWeek)
              ->setValues($stats)
        );
      }
      $metricsForActivity->setValue($total);
      $metrics[] = $metricsForActivity;
    }

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValue($totalPresences);
    $metric->setChildMetrics($metrics);
    return $metric;
  }

  protected function getLoans(string $identifier): Metric {
    $stats = $this->loanRepository->getLoanStats($this->club, $this->filterDates['end'], $this->filterDates['start']);

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValue($stats['total']);
    $metric->setValues($stats['dailyCounts']);

    $childMetrics = [
      new Metric()->setName('open-now')->setValue($stats['openNow']),
      new Metric()->setName('distinct-items')->setValue($stats['distinctItems']),
      new Metric()->setName('distinct-borrowers')->setValue($stats['distinctBorrowers']),
      new Metric()->setName('avg-duration-days')->setValue($stats['avgDurationDays']),
    ];

    foreach ($stats['items'] as $item) {
      $itemMetric = new Metric()
        ->setName($item['uuid'])
        ->setValue($item['count'])
        ->setValues($item['dailyCounts']);
      $itemMetric->setChildMetrics([
        new Metric()->setName('open-count')->setValue($item['openCount']),
        new Metric()->setName('avg-duration-days')->setValue($item['avgDurationDays']),
      ]);
      $childMetrics[] = $itemMetric;
    }

    $metric->setChildMetrics($childMetrics);

    return $metric;
  }

  protected function getImportBatches(string $identifier): Metric {
    $sql = "SELECT count(m.id) FROM messenger_messages m WHERE m.queue_name IN ('medium', 'low')";

    $res = $this->entityManager->getConnection()->prepare($sql)->executeQuery()->fetchOne();

    $metric = new Metric();
    $metric->setName($identifier);
    $metric->setValue($res);
    return $metric;
  }

  protected function getOpenedDays(string $identifier): Metric {
    $repository = $this->memberPresenceRepository;

    $openedDays = $repository->countNumberOfPresenceDaysYearlyUntilDate($this->club, $this->filterDates['end'], $this->filterDates['start']);

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValue($openedDays);
    return $metric;
  }

  protected function getMemberPresenceStats(string $identifier): Metric {
    $request = $this->requestStack->getCurrentRequest();

    $page = $request->query->getInt('page', 1);
    $itemsPerPage = $request->query->getInt('itemsPerPage', 30);

    // Club's control types drive the extra `control_<uuid>` sortable columns
    $controlTypes = $this->club ? $this->memberControlTypeRepository->findAllByClub($this->club) : [];

    // Parse API Platform-style order[field]=direction query params
    $allowedFields = ['presenceCount', 'lastPresenceDate', 'medicalCertificateExpiration'];
    foreach ($controlTypes as $controlType) {
      $allowedFields[] = 'control_' . str_replace('-', '_', (string) $controlType->getUuid());
    }
    $orderParam = $request->query->all('order');
    $orderBy = [];
    if (!empty($orderParam) && is_array($orderParam)) {
      foreach ($orderParam as $field => $direction) {
        $direction = strtoupper((string) $direction);
        if (in_array($field, $allowedFields, true) && in_array($direction, ['ASC', 'DESC'], true)) {
          $orderBy[$field] = $direction;
        }
      }
    }
    if (empty($orderBy)) {
      $orderBy = ['presenceCount' => 'DESC'];
    }

    // Get current season to enforce filter
    $currentSeason = $this->seasonRepository->findCurrentSeason($this->club);
    $values = $this->memberPresenceRepository->getMemberPresenceStats(
      $this->club,
      $this->filterDates['end'],
      $this->filterDates['start'],
      $orderBy,
      $page,
      $itemsPerPage,
      $currentSeason,
      $controlTypes
    );

    // Get total count for pagination metadata
    $totalItems = $this->memberRepository->countTotalClubMembers($this->club, $currentSeason);

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValues($values);

    $metric->setPagination(new MetricPagination(
      $page,
      $itemsPerPage,
      $totalItems,
      $itemsPerPage > 0 ? (int) ceil($totalItems / $itemsPerPage) : 0,
      $orderBy
    ));

    return $metric;
  }

  protected function getSalesStats(string $identifier): ?Metric {
    $request = $this->requestStack->getCurrentRequest();
    if (!$this->authorizationChecker->isGranted(Permission::SALE_HISTORY_ACCESS->value, $request)) {
      return null;
    }

    $stats = $this->saleRepository->getSaleStats($this->club, $this->filterDates['end'], $this->filterDates['start']);

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValue($stats['totalCount']);
    $metric->setValues($stats['paymentModes']);
    $metric->setChildMetrics([
      new Metric()->setName('total-count')->setValue($stats['totalCount']),
      new Metric()->setName('total-amount')->setValue($stats['totalAmount']),
    ]);

    return $metric;
  }

  protected function getSalesPerItemStats(string $identifier): ?Metric {
    $request = $this->requestStack->getCurrentRequest();
    if (!$this->authorizationChecker->isGranted(Permission::SALE_HISTORY_ACCESS->value, $request)) {
      return null;
    }

    $values = $this->saleRepository->getSalePerItemStats($this->club, $this->filterDates['end'], $this->filterDates['start']);

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValues($values);

    return $metric;
  }
}
