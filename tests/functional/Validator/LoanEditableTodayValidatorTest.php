<?php

namespace App\Tests\functional\Validator;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Tests\AbstractTestCase;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\MemberFactory;
use App\Validator\Constraints\LoanEditableToday;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LoanEditableTodayValidatorTest extends AbstractTestCase {
  private ValidatorInterface $validator;

  #[\Override]
  public function setUp(): void {
    parent::setUp();
    $this->validator = self::getContainer()->get(ValidatorInterface::class);
  }

  public function testNewLoanIsValidRegardlessOfStartDate(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable('-1 year'));

    $violations = $this->validator->validate($loan, new LoanEditableToday());
    $this->assertCount(0, $violations);
  }

  public function testAnyFieldChangeIsValidWhenLoanStartedToday(): void {
    $loan = LoanFactory::createOne(['startDate' => new \DateTimeImmutable(), 'endDate' => null]);
    $loan->setComment('Fixing a typo');

    $violations = $this->validator->validate($loan, new LoanEditableToday());
    $this->assertCount(0, $violations);
  }

  /**
   * isEditableToday() keys on createdAt, not startDate — a loan backdated to look like it started
   * long ago must remain freely editable on the day it was actually created, so mistakes can be undone.
   */
  public function testAnyFieldChangeIsValidWhenLoanCreatedTodayEvenIfBackdated(): void {
    $loan = LoanFactory::createOne(['startDate' => new \DateTimeImmutable('-1 year'), 'endDate' => null, 'createdAt' => new \DateTimeImmutable()]);
    $loan->setComment('Fixing a typo on a backdated loan');

    $violations = $this->validator->validate($loan, new LoanEditableToday());
    $this->assertCount(0, $violations);
  }

  /**
   * Returning a loan (endDate change) must work at any time, even on loans created long ago.
   */
  public function testReturningAnOldLoanIsValid(): void {
    $loan = LoanFactory::createOne(['startDate' => new \DateTimeImmutable('-1 year'), 'endDate' => null, 'createdAt' => new \DateTimeImmutable('-1 year')]);
    $loan->setEndDate(new \DateTimeImmutable());

    $violations = $this->validator->validate($loan, new LoanEditableToday());
    $this->assertCount(0, $violations);
  }

  public function testResubmittingUnchangedValuesOnAnOldLoanIsValid(): void {
    $loan = LoanFactory::createOne(['startDate' => new \DateTimeImmutable('-1 year'), 'endDate' => null, 'createdAt' => new \DateTimeImmutable('-1 year')]);

    $violations = $this->validator->validate($loan, new LoanEditableToday());
    $this->assertCount(0, $violations);
  }

  public function testEditingCommentOnAnOldLoanIsInvalid(): void {
    $loan = LoanFactory::createOne(['startDate' => new \DateTimeImmutable('-1 year'), 'endDate' => null, 'createdAt' => new \DateTimeImmutable('-1 year')]);
    $loan->setComment('Trying to fix a mistake after the fact');

    $violations = $this->validator->validate($loan, new LoanEditableToday());
    $this->assertCount(1, $violations);
    $this->assertEquals(
      'This loan can only be edited on the day it was created. Only the return date can be changed afterwards.',
      $violations->get(0)->getMessage()
    );
  }

  public function testEditingBorrowerOnAnOldLoanIsInvalid(): void {
    $member = MemberFactory::createOne();
    $loan = LoanFactory::createOne(['startDate' => new \DateTimeImmutable('-1 year'), 'endDate' => null, 'member' => null, 'createdAt' => new \DateTimeImmutable('-1 year')]);
    $loan->setMember($member);

    $violations = $this->validator->validate($loan, new LoanEditableToday());
    $this->assertCount(1, $violations);
  }
}
