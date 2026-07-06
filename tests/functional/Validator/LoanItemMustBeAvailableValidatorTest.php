<?php

namespace App\Tests\functional\Validator;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Enum\LoanItemStatus;
use App\Tests\AbstractTestCase;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\LoanItemFactory;
use App\Validator\Constraints\LoanItemMustBeAvailable;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LoanItemMustBeAvailableValidatorTest extends AbstractTestCase {
  private ValidatorInterface $validator;

  #[\Override]
  public function setUp(): void {
    parent::setUp();
    $this->validator = self::getContainer()->get(ValidatorInterface::class);
  }

  public function testNewLoanForAvailableItemIsValid(): void {
    $item = LoanItemFactory::createOne(['status' => LoanItemStatus::available]);
    $loan = new Loan();
    $loan->setLoanItem($item);

    $violations = $this->validator->validate($loan, new LoanItemMustBeAvailable());
    $this->assertCount(0, $violations);
  }

  public function testNewLoanForUnavailableItemIsInvalid(): void {
    $item = LoanItemFactory::createOne(['status' => LoanItemStatus::retired]);
    $loan = new Loan();
    $loan->setLoanItem($item);

    $violations = $this->validator->validate($loan, new LoanItemMustBeAvailable());
    $this->assertCount(1, $violations);
    $this->assertEquals('This item is not available for loan.', $violations->get(0)->getMessage());
  }

  /**
   * Updates (e.g. returning a loan) must not be blocked, even if the item's status changed
   * to non-available while it was out — the validator only enforces on creation.
   */
  public function testExistingLoanForUnavailableItemIsValid(): void {
    $item = LoanItemFactory::createOne(['status' => LoanItemStatus::available]);
    $loan = LoanFactory::createOne(['loanItem' => $item, 'endDate' => null]);
    $item->setStatus(LoanItemStatus::retired);

    $violations = $this->validator->validate($loan, new LoanItemMustBeAvailable());
    $this->assertCount(0, $violations);
  }

  public function testLoanWithoutLoanItemIsValid(): void {
    $loan = new Loan();

    $violations = $this->validator->validate($loan, new LoanItemMustBeAvailable());
    $this->assertCount(0, $violations);
  }
}
