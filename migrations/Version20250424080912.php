<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250424080912 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add club billing information';
  }

  public function up(Schema $schema): void {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD address VARCHAR(255) DEFAULT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD zip_code INT DEFAULT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD city VARCHAR(255) DEFAULT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD siret VARCHAR(255) DEFAULT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD vat VARCHAR(255) DEFAULT NULL
        SQL
    );
  }

  public function down(Schema $schema): void {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP address
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP zip_code
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP city
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP siret
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP vat
        SQL
    );
  }
}
