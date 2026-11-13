<?php

namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Recurring jobs, consumed by `messenger:consume scheduler_default` (the `scheduler` container).
 * Only one scheduler must run at a time.
 */
#[AsSchedule]
final readonly class MainSchedule implements ScheduleProviderInterface {
  private const string TIMEZONE = 'Europe/Paris';

  public function __construct(
    // Stored in the database so the last run survives a new image (var/ is not persisted)
    #[Autowire(service: 'cache.scheduler')]
    private CacheInterface $cache,
    private LockFactory $lockFactory,
  ) {
  }

  public function getSchedule(): Schedule {
    return new Schedule()
      ->add(
        RecurringMessage::cron('0 3 * * *', new RunCommandMessage('clean'), self::TIMEZONE),
        RecurringMessage::cron('15 3 1 * *', new RunCommandMessage('emailing:count:reset'), self::TIMEZONE),
      )
      // Runs the jobs missed while the scheduler was stopped, but only their last occurrence
      ->stateful($this->cache)
      ->processOnlyLastMissedRun(true)
      // A scheduler started by mistake next to the other one does not run the jobs twice
      ->lock($this->lockFactory->createLock('main_schedule'));
  }
}
