<?php

namespace App\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Make percentile sql query
 *
 * Usage : StringFunction PERCENTILE(x, expr)
 *
 */
class Percentile extends FunctionNode {
  private $x;
  private $expr;

  public function getSql(SqlWalker $sqlWalker): string {
    return 'PERCENTILE_CONT(' . $this->x->dispatch($sqlWalker) . ') WITHIN GROUP (ORDER BY ' . $this->expr->dispatch($sqlWalker) . ')';
  }

  public function parse(Parser $parser): void {
    $parser->match(TokenType::T_IDENTIFIER);
    $parser->match(TokenType::T_OPEN_PARENTHESIS);
    $this->x = $parser->ArithmeticPrimary();
    $parser->match(TokenType::T_COMMA);
    $this->expr = $parser->StringPrimary();
    $parser->match(TokenType::T_CLOSE_PARENTHESIS);
  }
}
