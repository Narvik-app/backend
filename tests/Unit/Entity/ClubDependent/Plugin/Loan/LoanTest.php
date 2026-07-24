<?php

namespace App\Tests\Unit\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use PHPUnit\Framework\TestCase;

class LoanTest extends TestCase {
  public function testIsEditableTodayWhenCreatedToday(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable());

    $this->assertTrue($loan->isEditableToday());
  }

  public function testIsEditableTodayWhenCreatedEarlierToday(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable('today')->setTime(0, 0, 1));

    $this->assertTrue($loan->isEditableToday());
  }

  public function testIsEditableTodayWhenCreatedYesterday(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable('-1 day'));

    $this->assertFalse($loan->isEditableToday());
  }

  public function testIsEditableTodayWhenCreatedLastYear(): void {
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable('-1 year'));

    $this->assertFalse($loan->isEditableToday());
  }

  public function testIsEditableTodayIgnoresBackdatedStartDate(): void {
    // A loan can be backdated (startDate in the past) while still having been created today —
    // isEditableToday() must key on createdAt, not startDate, so the creator can still fix mistakes.
    $loan = new Loan();
    $loan->setCreatedAt(new \DateTimeImmutable());
    $loan->setStartDate(new \DateTimeImmutable('-1 week'));

    $this->assertTrue($loan->isEditableToday());
  }
}
