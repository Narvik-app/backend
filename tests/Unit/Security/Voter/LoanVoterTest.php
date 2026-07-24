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

  public function testGrantsForLoanCreatedToday(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable());

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $loan, false],
      [Permission::LOAN_EDIT->value, $loan, true],
    ]);

    $voter = new LoanVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $loan, [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  public function testDeniesForLoanCreatedYesterday(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable('-1 day'));

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
   * A backdated loan (startDate in the past) that was created today must still be deletable
   * by its creator — isEditableToday() keys on createdAt, not startDate.
   */
  public function testGrantsForBackdatedLoanCreatedToday(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable());
    $loan->setStartDate(new \DateTimeImmutable('-1 week'));

    $security = $this->createStub(Security::class);
    $security->method('isGranted')->willReturnMap([
      [ClubRole::admin->value, $loan, false],
      [Permission::LOAN_EDIT->value, $loan, true],
    ]);

    $voter = new LoanVoter($security);
    $result = $voter->vote($this->tokenFor(new User()), $loan, [LoanVoter::DELETE]);
    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  /**
   * Admins can delete a loan regardless of when it was created.
   */
  public function testGrantsForAdminRegardlessOfCreatedAt(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable('-1 year'));

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
