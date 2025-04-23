<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250418091955 extends AbstractMigration {
  public function getDescription(): string {
    return 'Club can set a custom end for date for the season';
  }

  public function up(Schema $schema): void {
    $this->addSql(<<<'SQL'
            ALTER TABLE club_setting ADD season_end VARCHAR(5) DEFAULT '08-31' NOT NULL
        SQL
    );
  }

  public function down(Schema $schema): void {
    $this->addSql(<<<'SQL'
            ALTER TABLE club_setting DROP season_end
        SQL
    );
  }
}
