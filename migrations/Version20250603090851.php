<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250603090851 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add timestamp on File entity';
  }

  public function up(Schema $schema): void {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE file ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE file ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL
    );
  }

  public function down(Schema $schema): void {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE file DROP created_at
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE file DROP updated_at
        SQL
    );
  }
}
