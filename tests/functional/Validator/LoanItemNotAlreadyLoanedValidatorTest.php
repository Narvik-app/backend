<?php

namespace App\Tests\functional\Validator;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Tests\AbstractTestCase;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\LoanItemFactory;
use App\Validator\Constraints\LoanItemNotAlreadyLoaned;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LoanItemNotAlreadyLoanedValidatorTest extends AbstractTestCase {
  private ValidatorInterface $validator;

  #[\Override]
  public function setUp(): void {
    parent::setUp();
    $this->validator = self::getContainer()->get(ValidatorInterface::class);
  }

  public function testNewLoanForFreeItemIsValid(): void {
    $item = LoanItemFactory::createOne();
    $loan = new Loan();
    $loan->setLoanItem($item);

    $violations = $this->validator->validate($loan, new LoanItemNotAlreadyLoaned());
    $this->assertCount(0, $violations);
  }

  public function testNewLoanForAlreadyLoanedItemIsInvalid(): void {
    $item = LoanItemFactory::createOne();
    LoanFactory::createOne(['loanItem' => $item, 'endDate' => null]);

    $loan = new Loan();
    $loan->setLoanItem($item);

    $violations = $this->validator->validate($loan, new LoanItemNotAlreadyLoaned());
    $this->assertCount(1, $violations);
    $this->assertEquals('This item is already on loan.', $violations->get(0)->getMessage());
  }

  public function testNewLoanForItemWithOnlyClosedLoansIsValid(): void {
    $item = LoanItemFactory::createOne();
    LoanFactory::createOne(['loanItem' => $item, 'endDate' => new \DateTimeImmutable()]);

    $loan = new Loan();
    $loan->setLoanItem($item);

    $violations = $this->validator->validate($loan, new LoanItemNotAlreadyLoaned());
    $this->assertCount(0, $violations);
  }

  /**
   * Returning a loan (PATCH) must not be blocked by its own still-open state — the validator
   * only enforces on creation.
   */
  public function testExistingLoanOnAlreadyLoanedItemIsValid(): void {
    $item = LoanItemFactory::createOne();
    $loan = LoanFactory::createOne(['loanItem' => $item, 'endDate' => null]);

    $violations = $this->validator->validate($loan, new LoanItemNotAlreadyLoaned());
    $this->assertCount(0, $violations);
  }

  public function testLoanWithoutLoanItemIsValid(): void {
    $loan = new Loan();

    $violations = $this->validator->validate($loan, new LoanItemNotAlreadyLoaned());
    $this->assertCount(0, $violations);
  }
}
