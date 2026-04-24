<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417191920 extends AbstractMigration {
  public function getDescription(): string {
    return 'Rename control_shooting_activity_id to control_activity_id; add control_activity_alert_disabled on member';
  }

  public function up(Schema $schema): void {
    $this->addSql(<<<'SQL'
        ALTER TABLE club_setting RENAME COLUMN control_shooting_activity_id TO control_activity_id
    SQL
    );
    $this->addSql(<<<'SQL'
        ALTER TABLE member ADD control_activity_alert_disabled BOOLEAN DEFAULT false NOT NULL
    SQL
    );
  }

  public function down(Schema $schema): void {
    $this->addSql(<<<'SQL'
        ALTER TABLE member DROP control_activity_alert_disabled
    SQL
    );
    $this->addSql(<<<'SQL'
        ALTER TABLE club_setting RENAME COLUMN control_activity_id TO control_shooting_activity_id
    SQL
    );
  }
}
