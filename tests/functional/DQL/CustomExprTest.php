<?php

namespace App\Tests\functional\DQL;

use App\DQL\CustomExpr;
use App\Tests\AbstractTestCase;

class CustomExprTest extends AbstractTestCase
{
  public function testUnaccent()
  {
    $this->assertEquals('unaccent(LOWER(c.name))', CustomExpr::unaccentInsensitive('c.name'));
  }

  public function testDayOfWeek()
  {
    $this->assertEquals('dayofweek(c.createdAt)', CustomExpr::dayOfWeek('c.createdAt'));
  }

  public function testPercentile()
  {
    $this->assertEquals('percentile(95, c.createdAt)', CustomExpr::percentile(95, 'c.createdAt'));
  }
}
