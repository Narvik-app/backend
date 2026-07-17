<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714172941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sale_payment_terminal_connection (shared provider credentials) and sale_payment_terminal (TPE) entities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE sale_payment_terminal_connection_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sale_payment_terminal_connection (name VARCHAR(255) NOT NULL, provider VARCHAR(255) NOT NULL, available BOOLEAN NOT NULL, force_terminal_selection BOOLEAN DEFAULT false NOT NULL, credentials TEXT DEFAULT NULL, last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3C81E4ED17F50A6 ON sale_payment_terminal_connection (uuid)');
        $this->addSql('CREATE INDEX IDX_B3C81E4E61190A32 ON sale_payment_terminal_connection (club_id)');
        $this->addSql('ALTER TABLE sale_payment_terminal_connection ADD CONSTRAINT FK_B3C81E4E61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE SEQUENCE sale_payment_terminal_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sale_payment_terminal (name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, available BOOLEAN NOT NULL, external_device_id VARCHAR(255) NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, connection_id INT NOT NULL, payment_mode_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7F53063AD17F50A6 ON sale_payment_terminal (uuid)');
        $this->addSql('CREATE INDEX IDX_7F53063A61190A32 ON sale_payment_terminal (club_id)');
        $this->addSql('CREATE INDEX IDX_7F53063A6EAC8BA0 ON sale_payment_terminal (payment_mode_id)');
        $this->addSql('CREATE INDEX IDX_7F53063ADD03F01 ON sale_payment_terminal (connection_id)');
        $this->addSql('CREATE UNIQUE INDEX sale_payment_terminal_connection_device_unique ON sale_payment_terminal (connection_id, external_device_id)');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD CONSTRAINT FK_7F53063A61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD CONSTRAINT FK_7F53063A6EAC8BA0 FOREIGN KEY (payment_mode_id) REFERENCES sale_payment_mode (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD CONSTRAINT FK_7F53063ADD03F01 FOREIGN KEY (connection_id) REFERENCES sale_payment_terminal_connection (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('ALTER INDEX uniq_923c1d1ac94ed4a RENAME TO UNIQ_923C1D1A7E82CE84');
        $this->addSql('DROP INDEX idx_75ea56e016ba31db');
        $this->addSql('DROP INDEX idx_75ea56e0fb7336f0');
        $this->addSql('DROP INDEX idx_75ea56e0e3bd61ce');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sale_payment_terminal DROP CONSTRAINT FK_7F53063A61190A32');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP CONSTRAINT FK_7F53063A6EAC8BA0');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP CONSTRAINT FK_7F53063ADD03F01');
        $this->addSql('DROP TABLE sale_payment_terminal');
        $this->addSql('DROP SEQUENCE sale_payment_terminal_id_seq CASCADE');

        $this->addSql('ALTER TABLE sale_payment_terminal_connection DROP CONSTRAINT FK_B3C81E4E61190A32');
        $this->addSql('DROP TABLE sale_payment_terminal_connection');
        $this->addSql('DROP SEQUENCE sale_payment_terminal_connection_id_seq CASCADE');

        $this->addSql('ALTER INDEX uniq_923c1d1a7e82ce84 RENAME TO uniq_923c1d1ac94ed4a');
        $this->addSql('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
    }
}
