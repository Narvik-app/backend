<?php

namespace App\Tests\Unit\Service;

use App\Entity\Club;
use App\Entity\ClubDependent\ClubJob;
use App\Enum\ClubJobKey;
use App\Enum\ClubJobStatus;
use App\Mailer\EmailService;
use App\Repository\ClubDependent\ClubJobRepository;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubRepository;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Service\ClubService;
use App\Service\FileService;
use App\Service\GlobalSettingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ClubServiceJobTrackingTest extends TestCase {
  private function makeService(ClubJobRepository $clubJobRepository, ?ClubRepository $clubRepository = null, ?EntityManagerInterface $entityManager = null): ClubService {
    return new ClubService(
      $entityManager ?? $this->createStub(EntityManagerInterface::class),
      $this->createStub(UserRepository::class),
      $clubRepository ?? $this->createStub(ClubRepository::class),
      $this->createStub(EmailService::class),
      $this->createStub(ParameterBagInterface::class),
      $this->createStub(GlobalSettingService::class),
      $this->createStub(FileService::class),
      $this->createStub(FileRepository::class),
      $this->createStub(MemberRepository::class),
      $clubJobRepository,
    );
  }

  public function testStartJobCreatesANewClubJobWhenNoneExists(): void {
    $club = new Club();
    $persisted = null;

    $clubJobRepository = $this->createStub(ClubJobRepository::class);
    $clubJobRepository->method('findOneByClubAndKey')->willReturn(null);

    $em = $this->createStub(EntityManagerInterface::class);
    $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
      $persisted = $entity;
    });

    $service = $this->makeService($clubJobRepository, entityManager: $em);
    $service->startJob($club, ClubJobKey::itac_import, 5);

    $this->assertInstanceOf(ClubJob::class, $persisted);
    $this->assertSame($club, $persisted->getClub());
    $this->assertSame(ClubJobKey::itac_import, $persisted->getKey());
    $this->assertSame(5, $persisted->getTotal());
    $this->assertSame(5, $persisted->getRemaining());
    $this->assertSame(ClubJobStatus::in_progress, $persisted->getStatus());
  }

  public function testStartJobResetsAnExistingClubJob(): void {
    $club = new Club();
    $existing = new ClubJob()->setClub($club)->setKey(ClubJobKey::itac_import);
    $existing->setTotal(3)->setRemaining(0)->setStatus(ClubJobStatus::failed);

    $clubJobRepository = $this->createStub(ClubJobRepository::class);
    $clubJobRepository->method('findOneByClubAndKey')->willReturn($existing);

    $service = $this->makeService($clubJobRepository);
    $service->startJob($club, ClubJobKey::itac_import, 7);

    $this->assertSame(7, $existing->getTotal());
    $this->assertSame(7, $existing->getRemaining());
    $this->assertSame(ClubJobStatus::in_progress, $existing->getStatus());
  }

  public function testRecordJobResultDecrementsAndFinishesOnLastSuccess(): void {
    $club = new Club();
    $job = new ClubJob()->setClub($club)->setKey(ClubJobKey::member_control_sync);
    $job->setTotal(2)->setRemaining(1)->setStatus(ClubJobStatus::in_progress);

    $clubRepository = $this->createStub(ClubRepository::class);
    $clubRepository->method('findOneByUuid')->willReturn($club);
    $clubJobRepository = $this->createStub(ClubJobRepository::class);
    $clubJobRepository->method('findOneByClubAndKey')->willReturn($job);

    $service = $this->makeService($clubJobRepository, $clubRepository);
    $service->recordJobResult('any-uuid', ClubJobKey::member_control_sync, true);

    $this->assertSame(0, $job->getRemaining());
    $this->assertSame(ClubJobStatus::finished, $job->getStatus());
  }

  public function testRecordJobResultFailureSticksEvenAfterRemainingReachesZero(): void {
    $club = new Club();
    $job = new ClubJob()->setClub($club)->setKey(ClubJobKey::member_control_sync);
    $job->setTotal(2)->setRemaining(2)->setStatus(ClubJobStatus::in_progress);

    $clubRepository = $this->createStub(ClubRepository::class);
    $clubRepository->method('findOneByUuid')->willReturn($club);
    $clubJobRepository = $this->createStub(ClubJobRepository::class);
    $clubJobRepository->method('findOneByClubAndKey')->willReturn($job);

    $service = $this->makeService($clubJobRepository, $clubRepository);

    // First chunk fails permanently
    $service->recordJobResult('any-uuid', ClubJobKey::member_control_sync, false);
    $this->assertSame(1, $job->getRemaining());
    $this->assertSame(ClubJobStatus::failed, $job->getStatus());

    // Second (last) chunk succeeds — status must stay "failed", not flip to "finished"
    $service->recordJobResult('any-uuid', ClubJobKey::member_control_sync, true);
    $this->assertSame(0, $job->getRemaining());
    $this->assertSame(ClubJobStatus::failed, $job->getStatus());
  }

  public function testRecordJobResultIsANoOpWhenJobIsGone(): void {
    $club = new Club();
    $clubRepository = $this->createStub(ClubRepository::class);
    $clubRepository->method('findOneByUuid')->willReturn($club);
    $clubJobRepository = $this->createStub(ClubJobRepository::class);
    $clubJobRepository->method('findOneByClubAndKey')->willReturn(null);

    $service = $this->makeService($clubJobRepository, $clubRepository);
    $service->recordJobResult('any-uuid', ClubJobKey::member_control_sync, true);
    $this->addToAssertionCount(1); // no exception thrown
  }
}
