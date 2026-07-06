<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecording;
use App\Entity\User;
use App\Enum\Permission;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class LoanRecordingVoter extends Voter {
  public const string UPDATE = 'LOAN_RECORDING_UPDATE';
  public const string DELETE = 'LOAN_RECORDING_DELETE';

  public function __construct(
    private readonly Security $security
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    return $subject instanceof LoanRecording && in_array($attribute, [self::UPDATE, self::DELETE]);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    // Not a user or not a loan recording
    if (
      !$user instanceof User ||
      !$subject instanceof LoanRecording
    ) {
      return false;
    }

    if (!$this->security->isGranted(Permission::LOAN_RECORDINGS_EDIT->value, $subject)) {
      return false;
    }

    // Recordings can only be updated/deleted the day they were logged
    return $subject->isEditableToday();
  }
}
