<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\MetricPagination;
use App\Entity\Club;
use App\Entity\ClubDependent\Metric;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubDependent\MemberSeasonRepository;
use App\Repository\ClubDependent\Plugin\Presence\ExternalPresenceRepository;
use App\Repository\ClubDependent\Plugin\Presence\MemberPresenceRepository;
use App\Repository\Interface\PresenceRepositoryInterface;
use App\Service\RequestService;
use App\Service\SeasonService;
use App\Service\UtilsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MetricProvider implements ProviderInterface {
  public const array METRICS = [
    "members",
    "presences",
    "external-presences",
    "opened-days",
    "member-presence-stats",
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
    private readonly MemberPresenceRepository $memberPresenceRepository,
    private readonly ExternalPresenceRepository $externalPresenceRepository,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    $this->club = null;
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

    // Get and validate query parameters
    $order = strtoupper($request?->query->get('order', 'ASC'));
    if (!in_array($order, ['ASC', 'DESC'])) {
      throw new BadRequestHttpException('Invalid order parameter. Must be ASC or DESC.');
    }

    $page = $request?->query->get('page', 1);
    if (!is_numeric($page) || $page < 1) {
      throw new BadRequestHttpException('Invalid page parameter. Must be a positive integer.');
    }
    $page = (int) $page;

    $itemsPerPage = $request?->query->get('itemsPerPage', 30);
    if (!is_numeric($itemsPerPage) || $itemsPerPage < 1 || $itemsPerPage > 100) {
      throw new BadRequestHttpException('Invalid itemsPerPage parameter. Must be between 1 and 100.');
    }
    $itemsPerPage = (int) $itemsPerPage;

    // Get stats with pagination and ordering
    $stats = $this->memberPresenceRepository->getMemberPresenceStats(
      $this->club,
      $this->filterDates['end'],
      $this->filterDates['start'],
      $order,
      $page,
      $itemsPerPage
    );

    // Get total count for pagination metadata
    $totalItems = $this->memberPresenceRepository->countMemberPresenceStats(
      $this->club,
      $this->filterDates['end'],
      $this->filterDates['start']
    );

    $metric = new Metric();
    $metric->setClub($this->club);
    $metric->setName($identifier);
    $metric->setValues($stats);
    $metric->setPagination(new MetricPagination(
      $page,
      $itemsPerPage,
      $totalItems,
      $itemsPerPage > 0 ? (int) ceil($totalItems / $itemsPerPage) : 0,
      $order
    ));

    return $metric;
  }
}
