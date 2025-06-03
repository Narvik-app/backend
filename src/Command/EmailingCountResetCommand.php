<?php

namespace App\Command;

use App\Repository\ClubRepository;
use App\Repository\FileRepository;
use App\Repository\SeasonRepository;
use App\Repository\UserSecurityCodeRepository;
use App\Service\SeasonService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'emailing:count:reset', description: 'Reset club monthly email usage count. Will only allow to be run on the first of month')]
class EmailingCountResetCommand extends Command {
  private SymfonyStyle $io;

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly ClubRepository $clubRepository,
  ) {
    parent::__construct();
  }


  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io = new SymfonyStyle($input, $output);

    $this->resetClubsEmailCount();

    return Command::SUCCESS;
  }
  private function resetClubsEmailCount(): void {
    if (date('j') !== '1') { // We are not the first of the month, we do nothing
      $this->io->warning('Abort, not first day of the month.');
      return;
    }

    $clubs = $this->clubRepository->findAll();
    foreach ($clubs as $club) {
      $club->setCurrentMonthEmailsSent(0);
      $this->entityManager->persist($club);
    }

    $this->entityManager->flush();
  }


}
