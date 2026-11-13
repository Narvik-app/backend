<?php

namespace App\Command;

use App\Enum\GlobalSetting;
use App\Repository\ClubRepository;
use App\Service\GlobalSettingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Run by the scheduler (App\Scheduler\MainSchedule) at the start of each month.
 * Idempotent: the clubs are reset only once per month, so a run missed on the 1st can safely be caught up later.
 */
#[AsCommand(name: 'emailing:count:reset', description: 'Reset clubs monthly email usage count, once per month')]
class EmailingCountResetCommand extends Command {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly ClubRepository $clubRepository,
    private readonly GlobalSettingService $globalSettingService,
  ) {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $currentMonth = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))->format('Y-m');
    if ($this->globalSettingService->getSettingValue(GlobalSetting::EMAILING_COUNT_LAST_RESET) === $currentMonth) {
      $io->warning("Abort, email count already reset for $currentMonth.");
      return Command::SUCCESS;
    }

    foreach ($this->clubRepository->findAll() as $club) {
      $club->setCurrentMonthEmailsSent(0);
    }
    $this->globalSettingService->updateSettingValue(GlobalSetting::EMAILING_COUNT_LAST_RESET, $currentMonth);
    $this->entityManager->flush();

    $io->success("Email count reset for $currentMonth.");
    return Command::SUCCESS;
  }
}
