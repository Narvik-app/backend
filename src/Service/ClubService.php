<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Entity\File;
use App\Entity\User;
use App\Entity\UserMember;
use App\Enum\ClubRole;
use App\Enum\EmailStatus;
use App\Enum\GlobalSetting;
use App\Enum\UserRole;
use App\Mailer\EmailService;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\ClubRepository;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ClubService {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly UserRepository $userRepository,
    private readonly ClubRepository $clubRepository,
    private readonly EmailService $emailService,
    private readonly ParameterBagInterface $params,
    private readonly GlobalSettingService $globalSettingService,
    private readonly FileService $fileService,
    private readonly FileRepository $fileRepository,
    private readonly MemberRepository $memberRepository,
  ) {
  }

  /**
   * Automatically create the Badger user if not present.
   * Otherwise, just return it.
   *
   * It will check that the club has a badgerToken defined
   *
   * @param Club $club
   *
   * @return User|null
   */
  public function getBadger(Club $club): ?User {
    if (empty($club->getBadgerToken())) {
      return null;
    }

    // We try finding it
    $user = $this->userRepository->loadUserByIdentifier("badger@{$club->getUuid()->toString()}");
    if (!$user) {
      $user = new User();
      $user
        ->setAccountActivated(true)
        ->setFirstname('Badger')
        ->setLastname($club->getName() ?? 'BADGER')
        ->setRole(UserRole::badger)
        ->setEmail("badger@{$club->getUuid()->toString()}");

      // We link the user to the club
      $userMember = new UserMember();
      $userMember
        ->setUser($user)
        ->setBadgerClub($club)
        ->setRole(ClubRole::badger);

      $this->entityManager->persist($user);
      $this->entityManager->persist($userMember);
      $this->entityManager->flush();
      // We refresh so getLinkedProfiles() contain the club
      $this->entityManager->refresh($user);
    }

    // We check the club are matching
    $matched = false;
    foreach ($user->getLinkedProfiles() as $dbClub) {
      if ($dbClub->getClub() === $club) {
        $matched = true;
        break;
      }
    }

    if (!$matched) {
      return null;
    }

    return $user;
  }

  public function setItacImport(Club $club, int $numberOfBatches): void {
    $clubSettings = $club->getSettings();
    $clubSettings
      ->setItacImportRemaining($numberOfBatches)
      ->setItacImportDate(new \DateTimeImmutable());

    $this->entityManager->persist($clubSettings);
    $this->entityManager->flush();
  }

  public function setItacSecondaryImport(Club $club, int $numberOfBatches): void {
    $clubSettings = $club->getSettings();
    $clubSettings
      ->setItacSecondaryImportRemaining($numberOfBatches)
      ->setItacSecondaryImportDate(new \DateTimeImmutable());

    $this->entityManager->persist($clubSettings);
    $this->entityManager->flush();
  }

  public function setCerbereImport(Club $club, int $numberOfBatches): void {
    $clubSettings = $club->getSettings();
    $clubSettings
      ->setCerbereImportRemaining($numberOfBatches);

    $this->entityManager->persist($clubSettings);
    $this->entityManager->flush();
  }

  public function consumeMessage(string $clubUuid, string $clubSettingRemainingField): void {
    $club = $this->clubRepository->findOneByUuid($clubUuid);
    if (!$club instanceof Club) {
      return;
    }

    $getter = "get" . $clubSettingRemainingField;
    $setter = "set" . $clubSettingRemainingField;

    $clubSettings = $club->getSettings();
    $clubSettings
      ->$setter($clubSettings->$getter() - 1);

    $this->entityManager->persist($clubSettings);
    $this->entityManager->flush();
  }

  /**
   * Activate the free trial and send the email with all the legal documents
   * @param Club $club
   * @return void
   */
  public function activateTrial(Club $club): void {
    $trialEnd = new \DateTimeImmutable()->modify("+14 days");

    $club->setIsActivated(true);
    $club
      ->setRenewDate($trialEnd)
      ->setComment("Trial version end: " . $trialEnd->format("Y-m-d H:i:s"));

    // We enable all the modules
    $club
      ->setPresencesEnabled(true)
      ->setSalesEnabled(true);

    $this->entityManager->persist($club);
    $this->entityManager->flush();

    // Email notification
    $email = $this->emailService->getEmail("club/trial.html.twig", "Création de votre association sur Narvik", [
      'club' => $club,
    ]);

    // Copy to sales team
    $email->addBcc($this->params->get('app.sales_email'));

    // We join the pdf
    $cgv = $this->getLegalFile(GlobalSetting::LEGALS_CGV);
    if ($cgv) {
      $this->emailService->joinFile($email, $cgv, 'narvik-cgv.pdf');
    }
    $cgu = $this->getLegalFile(GlobalSetting::LEGALS_CGU);
    if ($cgv) {
      $this->emailService->joinFile($email, $cgu, 'narvik-cgu.pdf');
    }
    $privacy = $this->getLegalFile(GlobalSetting::LEGALS_PRIVACY_POLICY);
    if ($cgv) {
      $this->emailService->joinFile($email, $privacy, 'narvik-politique-confidentialite.pdf');
    }

    $this->emailService->sendEmail($email, $club->getContactEmail());
  }

  public function programDeletion(Club $club): void {
    $subscriptionEnd = new \DateTimeImmutable();
    if ($club->getRenewDate() > $subscriptionEnd) {
      $subscriptionEnd = $club->getRenewDate();
    }

    $subscriptionEnd = $subscriptionEnd->modify("+1 months");

    $club
      ->setDeletionDate($subscriptionEnd);

    $this->entityManager->persist($club);
    $this->entityManager->flush();

    // Email notification
    $email = $this->emailService->getEmail("club/programmed-removal.html.twig", "Suppression de votre association sur Narvik", [
      'club' => $club,
    ]);

    // Copy to sales team
    $email->addBcc($this->params->get('app.sales_email'));
    $this->emailService->sendEmail($email, $club->getContactEmail());
  }

  private function getLegalFile(GlobalSetting $globalSetting): ?File {
    $fileEncoded = $this->globalSettingService->getSettingValue($globalSetting);
    if (!$fileEncoded) {
      return null;
    }

    return $this->fileRepository->findOneByUuid($this->fileService->decodeEncodedUriId($fileEncoded));
  }

  public function linkUserToClub(Club $club, User $user, ClubRole $role = ClubRole::admin): void {
    // We create the member
    $member = new Member();
    $member
      ->setClub($club)
      ->setEmail($user->getEmail())
      ->setLastname($user->getLastname())
      ->setFirstname($user->getFirstname());

    $userMember = new UserMember();
    $userMember
      ->setUser($user)
      ->setMember($member)
      ->setRole($role);

    $this->entityManager->persist($member);
    $this->entityManager->persist($userMember);
  }

  /**
   * @param Club $club
   * @param User $user
   * @return int|null Number of email defined and so to be billed
   */
  private function applyClubMembersToEmail(Club $club, Email $email, TemplatedEmail $smtpEmail): ?int {
    $recipients = 0;
    if (empty($email->getMembers())) {
      return 0;
    }

    $members = $this->memberRepository->getAllByUuidsAndNewsletter($club, $email->getMembers(), $email->getIsNewsletter());
    // We send the email as cci so nobody can see other emails
    foreach ($members as $member) {
      if (!$member->getEmail()) {
        continue;
      }

      $smtpEmail->addBcc($member->getEmail());
      $recipients++;
    }

    return $recipients;
  }

  public function sendClubEmail(Club $club, Email $email): bool {
    if (empty($email->getMembers())) {
      $email->setStatus(EmailStatus::FAILED);
      $email->setRecipientCount(0);
      $email->setExplanation("No members defined.");
      return false;
    }

    // We generate the email template
    $smtpEmail = $this->emailService->getClubEmail($club, $email);
    if (!$smtpEmail) {
      $email->setStatus(EmailStatus::FAILED);
      $email->setRecipientCount(0);
      $email->setExplanation("Email not enabled.");
      return false;
    }

    $numberOfRecipients = $this->applyClubMembersToEmail($club, $email, $smtpEmail);
    $email->setRecipientCount($numberOfRecipients);

    if ($numberOfRecipients === 0) {
      $email->setStatus(EmailStatus::FAILED);
      $email->setExplanation("No matching members to send email to.");
      return false;
    }

    // Check quota is ok
    $emailLimit = $club->getMaxMonthlyEmails();
    $emailCurrentUsage = $club->getCurrentMonthEmailsSent();
    if ($emailCurrentUsage + $numberOfRecipients > $emailLimit) {
      $email->setStatus(EmailStatus::FAILED);
      $email->setExplanation("Monthly email limit reached.");
      return false;
    }

    // We add the attachement if any
    if ($email->getAttachment()) {
      $this->emailService->joinUploadedFile($smtpEmail, $email->getAttachment(), club: $email->getClub());
    }

    // We send the email
    $sent = $this->emailService->sendEmail($smtpEmail);

    if ($sent) {
      // Consume the quota
      $club->incrementCurrentMonthEmailsSent($numberOfRecipients);
      $email->setStatus(EmailStatus::SENT);
    } else {
      $email->setStatus(EmailStatus::FAILED);
      $email->setExplanation("Server error.");
      return false;
    }

    $this->entityManager->persist($email);
    $this->entityManager->persist($club);

    return true;
  }
}
