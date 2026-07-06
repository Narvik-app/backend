<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Security\Voter\LoanVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class LoanVoterTest extends TestCase {
  private function tokenFor(?User $user): TokenInterface {
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')->willReturn($user);
    return $token;
  }

  public function testAbstainsOnUnsupportedSubject(): void {
    $security = $this->createStub(Security::class);
    $voter = new LoanVoter($security);

    $result = $voter->vote($this->tokenFor(new User()), new \stdClass(), [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_ABSTAIN, $result);
  }

  public function testDeniesWithoutPermission(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable());

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $loan, false],
      [Permission::LOAN_EDIT->value, $loan, false],
    ]);

    $voter = new LoanVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $loan, [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  public function testGrantsForLoanStartedToday(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable());

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $loan, false],
      [Permission::LOAN_EDIT->value, $loan, true],
    ]);

    $voter = new LoanVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $loan, [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  public function testDeniesForLoanStartedYesterday(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable('-1 day'));

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $loan, false],
      [Permission::LOAN_EDIT->value, $loan, true],
    ]);

    $voter = new LoanVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $loan, [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  /**
   * Admins can delete a loan regardless of how long ago it was started.
   */
  public function testGrantsForAdminRegardlessOfStartDate(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable('-1 year'));

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $loan, true],
    ]);

    $voter = new LoanVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $loan, [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  public function testDeniesWhenNoUser(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable());

    $security = $this->createStub(Security::class);

    $voter = new LoanVoter($security);
    $result = $voter->vote($this->tokenFor(null), $loan, [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }
}
