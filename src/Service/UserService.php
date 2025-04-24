<?php

namespace App\Service;

use App\Entity\ClubDependent\Member;
use App\Entity\User;
use App\Entity\UserSecurityCode;
use App\Enum\ClubRole;
use App\Enum\UserSecurityCodeTrigger;
use App\Mailer\EmailService;
use App\Repository\UserRepository;
use App\Repository\UserSecurityCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserService {
  public function __construct(
    private readonly EntityManagerInterface $em,
    private readonly EmailService $emailService,
    private readonly UserPasswordHasherInterface $passwordHasher,
    private readonly UserRepository $userRepository,
    private readonly ValidatorInterface $validator,
    private readonly userSecurityCodeRepository $userSecurityCodeRepository,
  ) {
  }

  public function initiateAccountValidation(User $user, string $accountType = 'personal', bool $force = false): bool {
    if (!$force) {
      if ($user->isAccountActivated() || !$this->emailService->canSendEmail()) {
        return false;
      }
    }

    // We verify that we don't have more than 4 in progress reset for this user
    $validationInProgress = $this->userSecurityCodeRepository->findAllByTrigger($user, UserSecurityCodeTrigger::accountValidation);
    if (count($validationInProgress) > 3) {
      return false;
    }

    $securityCode = new UserSecurityCode();
    $securityCode->setTrigger(UserSecurityCodeTrigger::accountValidation)->setUser($user);

    $this->em->persist($securityCode);
    $this->em->flush();

    $frontendPath = "/login/register?security_code={$securityCode->getCode()}&account_type={$accountType}";
    $frontendPath .= '&email=' . urlencode($user->getEmail());

    // We send the security code
    $email = $this->emailService->getEmail('security-code.html.twig', 'Validation du compte', [
      'security_code' => $securityCode->getCode(),
      'frontend_path' => $frontendPath,
    ]);
    $this->emailService->sendEmail($email, $user->getEmail());

    return true;
  }

  public function activateAccount(User $user, string $firstname, string $lastname, string $password): void {
    // We validate the password
    $errorPassword = $this->validateUserPassword($password);
    if ($errorPassword) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, $errorPassword);
    }

    $user
      ->setAccountActivated(true)
      ->setLegalsAccepted(new \DateTimeImmutable())
      ->setFirstname($firstname)
      ->setlastname($lastname);

    $errors = $this->validator->validate($user);
    if (count($errors) > 0) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, $errors);
    }

    $this->changeUserPassword($user, $password, false);

    $this->em->persist($user);
    $this->em->flush();
  }

  /**
   * @param string $password
   * @return string|null Error message or null if ever everything is ok
   */
  private function validateUserPassword(string $password): ?string {
    if (empty($password) || strlen($password) < 8) {
      return 'Password must be at least 8 letters long.';
    }

    return null;
  }

  /**
   * @param User $user
   * @param string $password
   * @param bool $flush
   * @return string|null Error message or null if ever everything is ok
   */
  public function changeUserPassword(User $user, string $password, bool $flush = true): ?string {
    $errorPassword = $this->validateUserPassword($password);
    if ($errorPassword) {
      return $errorPassword;
    }

    $this->userRepository->upgradePassword($user, $this->passwordHasher->hashPassword($user, $password), $flush);

    return null;
  }

  public function initiateResetPassword(User $user): bool {
    if (!$user->isAccountActivated() || !$this->emailService->canSendEmail()) {
      return false;
    }

    // We verify that we don't have more than 4 in progress reset for this user
    $resetInProgress = $this->userSecurityCodeRepository->findAllByTrigger($user, UserSecurityCodeTrigger::resetPassword);
    if (count($resetInProgress) > 3) {
      return false;
    }

    $securityCode = new UserSecurityCode();
    $securityCode->setTrigger(UserSecurityCodeTrigger::resetPassword)->setUser($user);

    $this->em->persist($securityCode);
    $this->em->flush();

    $frontendPath = "/login/password-reset?security_code={$securityCode->getCode()}";
    $frontendPath .= '&email=' . urlencode($user->getEmail());

    // We sent the security code
    $email = $this->emailService->getEmail('security-code.html.twig', 'Changement de mot de passe', [
      'security_code' => $securityCode->getCode(),
      'frontend_path' => $frontendPath,
    ]);
    $this->emailService->sendEmail($email, $user->getEmail());

    return true;
  }

  public function validateSecurityCode(User $user, UserSecurityCodeTrigger $trigger, string $securityCode): bool {
    $securityCodeQuery = $this->userSecurityCodeRepository->findLastOneForUser($user, $trigger);
    if (!$securityCodeQuery || $securityCodeQuery->getCode() !== trim($securityCode)) {
      return false;
    }
    return true;
  }

  public function consumeAllSecurityCodes(User $user, UserSecurityCodeTrigger $trigger): void {
    $codes = $this->userSecurityCodeRepository->findAllByTrigger($user, $trigger);
    foreach ($codes as $code) {
      $this->em->remove($code);
    }
    $this->em->flush();
  }

  public function createOrGetFromMember(Member $member): ?User {
    if (!$member->getEmail()) {
      return null;
    }

    $user = $this->userRepository->findOneByEmail($member->getEmail());
    if (!$user) {
      $user = new User();
      $user
        ->setEmail($member->getEmail())
        ->setFirstname($member->getFirstname())
        ->setLastname($member->getLastname())
        ->setAccountActivated(false);
      $this->em->persist($user);
      $this->em->flush();
    }

    return $user;
  }
}
