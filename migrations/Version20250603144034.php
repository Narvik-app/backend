<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250603144034 extends AbstractMigration {
  public function getDescription(): string {
    return 'Add emailing for clubs';
  }

  public function up(Schema $schema): void {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            CREATE SEQUENCE email_id_seq INCREMENT BY 1 MINVALUE 1 START 1
        SQL
    );
    $this->addSql(<<<'SQL'
            CREATE TABLE email (status VARCHAR(255) NOT NULL, explanation VARCHAR(255) DEFAULT NULL, is_newsletter BOOLEAN NOT NULL, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, recipient_count INT NOT NULL, sender VARCHAR(255) NOT NULL, reply_to VARCHAR(255) DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY(id))
        SQL
    );
    $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_E7927C74D17F50A6 ON email (uuid)
        SQL
    );
    $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E7927C7461190A32 ON email (club_id)
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE email ADD CONSTRAINT FK_E7927C7461190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD current_month_emails_sent INT DEFAULT 0 NOT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club ADD max_monthly_emails INT DEFAULT 200 NOT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club_setting ADD email_reply_to VARCHAR(255) DEFAULT NULL
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE member ADD club_newsletter BOOLEAN DEFAULT true NOT NULL
        SQL
    );
  }

  public function down(Schema $schema): void {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql(<<<'SQL'
            DROP SEQUENCE email_id_seq CASCADE
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE email DROP CONSTRAINT FK_E7927C7461190A32
        SQL
    );
    $this->addSql(<<<'SQL'
            DROP TABLE email
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP current_month_emails_sent
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club DROP max_monthly_emails
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE member DROP club_newsletter
        SQL
    );
    $this->addSql(<<<'SQL'
            ALTER TABLE club_setting DROP email_reply_to
        SQL
    );
  }
}
