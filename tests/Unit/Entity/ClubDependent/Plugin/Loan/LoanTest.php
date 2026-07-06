<?php

namespace App\Tests\Unit\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use PHPUnit\Framework\TestCase;

class LoanTest extends TestCase {
  public function testIsEditableTodayWhenStartedToday(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable());

    $this->assertTrue($loan->isEditableToday());
  }

  public function testIsEditableTodayWhenStartedEarlierToday(): void {
    $loan = new Loan();
    $loan->setStartDate((new \DateTimeImmutable('today'))->setTime(0, 0, 1));

    $this->assertTrue($loan->isEditableToday());
  }

  public function testIsEditableTodayWhenStartedYesterday(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable('-1 day'));

    $this->assertFalse($loan->isEditableToday());
  }

  public function testIsEditableTodayWhenStartedLastYear(): void {
    $loan = new Loan();
    $loan->setStartDate(new \DateTimeImmutable('-1 year'));

    $this->assertFalse($loan->isEditableToday());
  }
}
