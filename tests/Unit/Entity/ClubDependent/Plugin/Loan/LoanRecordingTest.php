<?php

namespace App\Tests\Unit\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecording;
use PHPUnit\Framework\TestCase;

class LoanRecordingTest extends TestCase {
  public function testIsEditableTodayWhenCreatedToday(): void {
    $recording = new LoanRecording();
    $recording->setCreatedAt(new \DateTimeImmutable());

    $this->assertTrue($recording->isEditableToday());
  }

  public function testIsEditableTodayWhenCreatedEarlierToday(): void {
    $recording = new LoanRecording();
    $recording->setCreatedAt(new \DateTimeImmutable('today')->setTime(0, 0, 1));

    $this->assertTrue($recording->isEditableToday());
  }

  public function testIsEditableTodayWhenCreatedYesterday(): void {
    $recording = new LoanRecording();
    $recording->setCreatedAt(new \DateTimeImmutable('-1 day'));

    $this->assertFalse($recording->isEditableToday());
  }

  public function testIsEditableTodayWhenCreatedLastYear(): void {
    $recording = new LoanRecording();
    $recording->setCreatedAt(new \DateTimeImmutable('-1 year'));

    $this->assertFalse($recording->isEditableToday());
  }

  public function testIsEditableTodayIgnoresBackdatedDate(): void {
    // A recording can be backdated (date in the past) while still having been created today —
    // isEditableToday() must key on createdAt, not date, so the creator can still fix mistakes.
    $recording = new LoanRecording();
    $recording->setCreatedAt(new \DateTimeImmutable());
    $recording->setDate(new \DateTimeImmutable('-1 week'));

    $this->assertTrue($recording->isEditableToday());
  }
}
