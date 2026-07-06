<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecording;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Security\Voter\LoanRecordingVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class LoanRecordingVoterTest extends TestCase {
  private function tokenFor(?User $user): TokenInterface {
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')->willReturn($user);
    return $token;
  }

  public function testAbstainsOnUnsupportedSubject(): void {
    $security = $this->createStub(Security::class);
    $voter = new LoanRecordingVoter($security);

    $result = $voter->vote($this->tokenFor(new User()), new \stdClass(), [LoanRecordingVoter::UPDATE]);
    $this->assertSame(Voter::ACCESS_ABSTAIN, $result);
  }

  public function testDeniesWithoutPermission(): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable());

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $recording, false],
      [Permission::LOAN_RECORDINGS_EDIT->value, $recording, false],
    ]);

    $voter = new LoanRecordingVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $recording, [LoanRecordingVoter::UPDATE]);
    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  #[DataProvider('provideAttributes')]
  public function testGrantsForRecordingLoggedToday(string $attribute): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable());

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $recording, false],
      [Permission::LOAN_RECORDINGS_EDIT->value, $recording, true],
    ]);

    $voter = new LoanRecordingVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $recording, [$attribute]);
    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  #[DataProvider('provideAttributes')]
  public function testDeniesForRecordingLoggedYesterday(string $attribute): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable('-1 day'));

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $recording, false],
      [Permission::LOAN_RECORDINGS_EDIT->value, $recording, true],
    ]);

    $voter = new LoanRecordingVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $recording, [$attribute]);
    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  /**
   * Admins can update/delete a recording regardless of how long ago it was logged.
   */
  #[DataProvider('provideAttributes')]
  public function testGrantsForAdminRegardlessOfDate(string $attribute): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable('-1 year'));

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $recording, true],
    ]);

    $voter = new LoanRecordingVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $recording, [$attribute]);
    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  public function testDeniesWhenNoUser(): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable());

    $security = $this->createStub(Security::class);

    $voter = new LoanRecordingVoter($security);
    $result = $voter->vote($this->tokenFor(null), $recording, [LoanRecordingVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  public static function provideAttributes(): \Generator {
    yield 'update' => [LoanRecordingVoter::UPDATE];
    yield 'delete' => [LoanRecordingVoter::DELETE];
  }
}
