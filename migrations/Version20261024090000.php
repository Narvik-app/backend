<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024090000 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add member_control_type.alert_if_missing to flag members with no date for a control';
  }

  public function up(Schema $schema): void {
    $this->addSql('ALTER TABLE member_control_type ADD alert_if_missing BOOLEAN DEFAULT false NOT NULL');
  }

  public function down(Schema $schema): void {
    $this->addSql('ALTER TABLE member_control_type DROP alert_if_missing');
  }
}
