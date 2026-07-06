<?php

namespace App\Tests\Unit\Entity\ClubDependent\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanRecording;
use PHPUnit\Framework\TestCase;

class LoanRecordingTest extends TestCase {
  public function testIsEditableTodayWhenLoggedToday(): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable());

    $this->assertTrue($recording->isEditableToday());
  }

  public function testIsEditableTodayWhenLoggedEarlierToday(): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable('today')->setTime(0, 0, 1));

    $this->assertTrue($recording->isEditableToday());
  }

  public function testIsEditableTodayWhenLoggedYesterday(): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable('-1 day'));

    $this->assertFalse($recording->isEditableToday());
  }

  public function testIsEditableTodayWhenLoggedLastYear(): void {
    $recording = new LoanRecording();
    $recording->setDate(new \DateTimeImmutable('-1 year'));

    $this->assertFalse($recording->isEditableToday());
  }
}
