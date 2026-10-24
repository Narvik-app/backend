<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024100000 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add time_and_travel_export.regeneration_failed to flag exports whose background generation failed';
  }

  public function up(Schema $schema): void {
    $this->addSql('ALTER TABLE time_and_travel_export ADD regeneration_failed BOOLEAN DEFAULT false NOT NULL');
  }

  public function down(Schema $schema): void {
    $this->addSql('ALTER TABLE time_and_travel_export DROP regeneration_failed');
  }
}
