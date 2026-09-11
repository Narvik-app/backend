<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260911160038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Time and Travel Declaration plugin (declarations, vehicles, exports and attestations)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE member_vehicle_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE time_and_travel_declaration_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE time_and_travel_export_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE time_and_travel_export_attestation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE member_vehicle (brand VARCHAR(255) NOT NULL, model VARCHAR(255) DEFAULT NULL, license_plate VARCHAR(20) NOT NULL, engine_type VARCHAR(255) NOT NULL, fiscal_power INT NOT NULL, fiscal_coefficient NUMERIC(8, 4) NOT NULL, is_enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, member_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A4745312D17F50A6 ON member_vehicle (uuid)');
        $this->addSql('CREATE INDEX IDX_A47453127597D3FE ON member_vehicle (member_id)');
        $this->addSql('CREATE INDEX IDX_A474531261190A32 ON member_vehicle (club_id)');
        $this->addSql('CREATE TABLE time_and_travel_declaration (date DATE NOT NULL, departure_location VARCHAR(255) DEFAULT NULL, arrival_location VARCHAR(255) DEFAULT NULL, kilometers INT DEFAULT NULL, hours NUMERIC(4, 2) DEFAULT NULL, description VARCHAR(255) NOT NULL, is_roundtrip BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, member_id INT DEFAULT NULL, member_vehicle_id INT DEFAULT NULL, member_presence_id INT DEFAULT NULL, export_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6289DC74D17F50A6 ON time_and_travel_declaration (uuid)');
        $this->addSql('CREATE INDEX idx_tt_declaration_club_date ON time_and_travel_declaration (club_id, date)');
        $this->addSql('CREATE INDEX idx_tt_declaration_member_date ON time_and_travel_declaration (member_id, date)');
        $this->addSql('CREATE INDEX IDX_6289DC747597D3FE ON time_and_travel_declaration (member_id)');
        $this->addSql('CREATE INDEX IDX_6289DC74BF1D0938 ON time_and_travel_declaration (member_vehicle_id)');
        $this->addSql('CREATE INDEX IDX_6289DC742A15EB06 ON time_and_travel_declaration (member_presence_id)');
        $this->addSql('CREATE INDEX IDX_6289DC7464CDAF82 ON time_and_travel_declaration (export_id)');
        $this->addSql('CREATE INDEX IDX_6289DC7461190A32 ON time_and_travel_declaration (club_id)');
        $this->addSql('CREATE TABLE time_and_travel_export (status VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, label VARCHAR(255) DEFAULT NULL, smic_hourly_rate NUMERIC(6, 2) DEFAULT NULL, locked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, unlocked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, generated_by_id INT DEFAULT NULL, locked_by_id INT DEFAULT NULL, unlocked_by_id INT DEFAULT NULL, recap_file_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_53A200E0D17F50A6 ON time_and_travel_export (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_53A200E0E03C8F8F ON time_and_travel_export (recap_file_id)');
        $this->addSql('CREATE INDEX idx_tt_export_club_status ON time_and_travel_export (club_id, status)');
        $this->addSql('CREATE INDEX IDX_53A200E01BDD81B ON time_and_travel_export (generated_by_id)');
        $this->addSql('CREATE INDEX IDX_53A200E07A88E00 ON time_and_travel_export (locked_by_id)');
        $this->addSql('CREATE INDEX IDX_53A200E0371F3A6E ON time_and_travel_export (unlocked_by_id)');
        $this->addSql('CREATE INDEX IDX_53A200E061190A32 ON time_and_travel_export (club_id)');
        $this->addSql('CREATE TABLE time_and_travel_export_attestation (total_kilometers INT NOT NULL, total_hours NUMERIC(6, 2) NOT NULL, total_travel_amount NUMERIC(10, 2) NOT NULL, total_time_amount NUMERIC(10, 2) NOT NULL, total_amount_persisted NUMERIC(10, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, export_id INT NOT NULL, member_id INT DEFAULT NULL, file_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C234D076D17F50A6 ON time_and_travel_export_attestation (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C234D07693CB796C ON time_and_travel_export_attestation (file_id)');
        $this->addSql('CREATE INDEX IDX_C234D07664CDAF82 ON time_and_travel_export_attestation (export_id)');
        $this->addSql('CREATE INDEX IDX_C234D0767597D3FE ON time_and_travel_export_attestation (member_id)');
        $this->addSql('CREATE INDEX IDX_C234D07661190A32 ON time_and_travel_export_attestation (club_id)');
        $this->addSql('ALTER TABLE member_vehicle ADD CONSTRAINT FK_A47453127597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE member_vehicle ADD CONSTRAINT FK_A474531261190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC747597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC74BF1D0938 FOREIGN KEY (member_vehicle_id) REFERENCES member_vehicle (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC742A15EB06 FOREIGN KEY (member_presence_id) REFERENCES member_presence (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC7464CDAF82 FOREIGN KEY (export_id) REFERENCES time_and_travel_export (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_declaration ADD CONSTRAINT FK_6289DC7461190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export ADD CONSTRAINT FK_53A200E01BDD81B FOREIGN KEY (generated_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export ADD CONSTRAINT FK_53A200E07A88E00 FOREIGN KEY (locked_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export ADD CONSTRAINT FK_53A200E0371F3A6E FOREIGN KEY (unlocked_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export ADD CONSTRAINT FK_53A200E0E03C8F8F FOREIGN KEY (recap_file_id) REFERENCES file (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export ADD CONSTRAINT FK_53A200E061190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation ADD CONSTRAINT FK_C234D07664CDAF82 FOREIGN KEY (export_id) REFERENCES time_and_travel_export (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation ADD CONSTRAINT FK_C234D0767597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation ADD CONSTRAINT FK_C234D07693CB796C FOREIGN KEY (file_id) REFERENCES file (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation ADD CONSTRAINT FK_C234D07661190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE activity ADD prompt_time_and_travel_declaration BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE club ADD time_and_travel_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE club_setting ADD smic_hourly_rate NUMERIC(6, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE member_vehicle_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE time_and_travel_declaration_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE time_and_travel_export_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE time_and_travel_export_attestation_id_seq CASCADE');
        $this->addSql('ALTER TABLE member_vehicle DROP CONSTRAINT FK_A47453127597D3FE');
        $this->addSql('ALTER TABLE member_vehicle DROP CONSTRAINT FK_A474531261190A32');
        $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC747597D3FE');
        $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC74BF1D0938');
        $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC742A15EB06');
        $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC7464CDAF82');
        $this->addSql('ALTER TABLE time_and_travel_declaration DROP CONSTRAINT FK_6289DC7461190A32');
        $this->addSql('ALTER TABLE time_and_travel_export DROP CONSTRAINT FK_53A200E01BDD81B');
        $this->addSql('ALTER TABLE time_and_travel_export DROP CONSTRAINT FK_53A200E07A88E00');
        $this->addSql('ALTER TABLE time_and_travel_export DROP CONSTRAINT FK_53A200E0371F3A6E');
        $this->addSql('ALTER TABLE time_and_travel_export DROP CONSTRAINT FK_53A200E0E03C8F8F');
        $this->addSql('ALTER TABLE time_and_travel_export DROP CONSTRAINT FK_53A200E061190A32');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation DROP CONSTRAINT FK_C234D07664CDAF82');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation DROP CONSTRAINT FK_C234D0767597D3FE');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation DROP CONSTRAINT FK_C234D07693CB796C');
        $this->addSql('ALTER TABLE time_and_travel_export_attestation DROP CONSTRAINT FK_C234D07661190A32');
        $this->addSql('DROP TABLE member_vehicle');
        $this->addSql('DROP TABLE time_and_travel_declaration');
        $this->addSql('DROP TABLE time_and_travel_export');
        $this->addSql('DROP TABLE time_and_travel_export_attestation');
        $this->addSql('ALTER TABLE activity DROP prompt_time_and_travel_declaration');
        $this->addSql('ALTER TABLE club DROP time_and_travel_enabled');
        $this->addSql('ALTER TABLE club_setting DROP smic_hourly_rate');
    }
}
