<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714102424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split SalePaymentTerminal into a shared SalePaymentTerminalConnection (credentials) and per-device rows (name/icon/paymentMode/externalDeviceId)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE sale_payment_terminal_connection_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sale_payment_terminal_connection (name VARCHAR(255) NOT NULL, provider VARCHAR(255) DEFAULT \'sumup\' NOT NULL, available BOOLEAN NOT NULL, credentials TEXT DEFAULT NULL, last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3C81E4ED17F50A6 ON sale_payment_terminal_connection (uuid)');
        $this->addSql('CREATE INDEX IDX_B3C81E4E61190A32 ON sale_payment_terminal_connection (club_id)');
        $this->addSql('ALTER TABLE sale_payment_terminal_connection ADD CONSTRAINT FK_B3C81E4E61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD external_device_id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD connection_id INT NOT NULL');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP provider');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP credentials');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD CONSTRAINT FK_7F53063ADD03F01 FOREIGN KEY (connection_id) REFERENCES sale_payment_terminal_connection (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_7F53063ADD03F01 ON sale_payment_terminal (connection_id)');
        $this->addSql('CREATE UNIQUE INDEX sale_payment_terminal_connection_device_unique ON sale_payment_terminal (connection_id, external_device_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE sale_payment_terminal_connection_id_seq CASCADE');
        $this->addSql('ALTER TABLE sale_payment_terminal_connection DROP CONSTRAINT FK_B3C81E4E61190A32');
        $this->addSql('DROP TABLE sale_payment_terminal_connection');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP CONSTRAINT FK_7F53063ADD03F01');
        $this->addSql('DROP INDEX IDX_7F53063ADD03F01');
        $this->addSql('DROP INDEX sale_payment_terminal_connection_device_unique');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD provider VARCHAR(255) DEFAULT \'sumup\' NOT NULL');
        $this->addSql('ALTER TABLE sale_payment_terminal ADD credentials TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP external_device_id');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP last_seen_at');
        $this->addSql('ALTER TABLE sale_payment_terminal DROP connection_id');
    }
}
