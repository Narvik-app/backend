<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250911101117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email template';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE email_template_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE email_template (name VARCHAR(255) NOT NULL, is_newsletter BOOLEAN NOT NULL, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, id INT NOT NULL, uuid UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9C0600CAD17F50A6 ON email_template (uuid)');
        $this->addSql('CREATE INDEX IDX_9C0600CA61190A32 ON email_template (club_id)');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CA61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE email_template_id_seq CASCADE');
        $this->addSql('ALTER TABLE email_template DROP CONSTRAINT FK_9C0600CA61190A32');
        $this->addSql('DROP TABLE email_template');
    }
}
