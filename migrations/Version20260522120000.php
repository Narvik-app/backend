<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522120000 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add kind discriminator on sale_payment_mode (payment | stock_removal)';
  }

  public function up(Schema $schema): void {
    $this->addSql('ALTER TABLE sale_payment_mode ADD kind VARCHAR(255) DEFAULT \'payment\' NOT NULL');
  }

  public function down(Schema $schema): void {
    $this->addSql('ALTER TABLE sale_payment_mode DROP kind');
  }
}
