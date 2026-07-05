<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623130845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE loan_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE loan_category_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE loan_item_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE loan_recording_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE loan_recording_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE loan (start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, comment VARCHAR(1024) DEFAULT NULL, borrower_name VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, loan_item_id INT NOT NULL, member_id INT DEFAULT NULL, author_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C5D30D03D17F50A6 ON loan (uuid)');
        $this->addSql('CREATE INDEX IDX_C5D30D033C20DC2A ON loan (loan_item_id)');
        $this->addSql('CREATE INDEX IDX_C5D30D037597D3FE ON loan (member_id)');
        $this->addSql('CREATE INDEX IDX_C5D30D03F675F31B ON loan (author_id)');
        $this->addSql('CREATE INDEX IDX_C5D30D0361190A32 ON loan (club_id)');
        $this->addSql('CREATE TABLE loan_category (name VARCHAR(255) NOT NULL, weight INT DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_510AE36AD17F50A6 ON loan_category (uuid)');
        $this->addSql('CREATE INDEX IDX_510AE36A61190A32 ON loan_category (club_id)');
        $this->addSql('CREATE TABLE loan_item (name VARCHAR(255) NOT NULL, description VARCHAR(1024) DEFAULT NULL, loan_price NUMERIC(8, 2) DEFAULT NULL, purchase_price NUMERIC(8, 2) DEFAULT NULL, sold_price NUMERIC(8, 2) DEFAULT NULL, status VARCHAR(50) NOT NULL, weight INT DEFAULT NULL, visible_on_sale_page BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, category_id INT DEFAULT NULL, image_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CEB65F6AD17F50A6 ON loan_item (uuid)');
        $this->addSql('CREATE INDEX IDX_CEB65F6A12469DE2 ON loan_item (category_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CEB65F6A3DA5256D ON loan_item (image_id)');
        $this->addSql('CREATE INDEX IDX_CEB65F6A61190A32 ON loan_item (club_id)');
        $this->addSql('CREATE TABLE loan_recording (description VARCHAR(2048) DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, loan_item_id INT NOT NULL, recording_type_id INT DEFAULT NULL, author_id INT DEFAULT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FA0017C9D17F50A6 ON loan_recording (uuid)');
        $this->addSql('CREATE INDEX IDX_FA0017C93C20DC2A ON loan_recording (loan_item_id)');
        $this->addSql('CREATE INDEX IDX_FA0017C94A1AB7F1 ON loan_recording (recording_type_id)');
        $this->addSql('CREATE INDEX IDX_FA0017C9F675F31B ON loan_recording (author_id)');
        $this->addSql('CREATE INDEX IDX_FA0017C961190A32 ON loan_recording (club_id)');
        $this->addSql('CREATE TABLE loan_recording_type (name VARCHAR(255) NOT NULL, color VARCHAR(20) DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, club_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1663CB89D17F50A6 ON loan_recording_type (uuid)');
        $this->addSql('CREATE INDEX IDX_1663CB8961190A32 ON loan_recording_type (club_id)');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D033C20DC2A FOREIGN KEY (loan_item_id) REFERENCES loan_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D037597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D03F675F31B FOREIGN KEY (author_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D0361190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_category ADD CONSTRAINT FK_510AE36A61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_item ADD CONSTRAINT FK_CEB65F6A12469DE2 FOREIGN KEY (category_id) REFERENCES loan_category (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_item ADD CONSTRAINT FK_CEB65F6A3DA5256D FOREIGN KEY (image_id) REFERENCES file (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_item ADD CONSTRAINT FK_CEB65F6A61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_recording ADD CONSTRAINT FK_FA0017C93C20DC2A FOREIGN KEY (loan_item_id) REFERENCES loan_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_recording ADD CONSTRAINT FK_FA0017C94A1AB7F1 FOREIGN KEY (recording_type_id) REFERENCES loan_recording_type (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_recording ADD CONSTRAINT FK_FA0017C9F675F31B FOREIGN KEY (author_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_recording ADD CONSTRAINT FK_FA0017C961190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE loan_recording_type ADD CONSTRAINT FK_1663CB8961190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE club ADD loans_enabled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE loan_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE loan_category_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE loan_item_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE loan_recording_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE loan_recording_type_id_seq CASCADE');
        $this->addSql('ALTER TABLE loan DROP CONSTRAINT FK_C5D30D033C20DC2A');
        $this->addSql('ALTER TABLE loan DROP CONSTRAINT FK_C5D30D037597D3FE');
        $this->addSql('ALTER TABLE loan DROP CONSTRAINT FK_C5D30D03F675F31B');
        $this->addSql('ALTER TABLE loan DROP CONSTRAINT FK_C5D30D0361190A32');
        $this->addSql('ALTER TABLE loan_category DROP CONSTRAINT FK_510AE36A61190A32');
        $this->addSql('ALTER TABLE loan_item DROP CONSTRAINT FK_CEB65F6A12469DE2');
        $this->addSql('ALTER TABLE loan_item DROP CONSTRAINT FK_CEB65F6A3DA5256D');
        $this->addSql('ALTER TABLE loan_item DROP CONSTRAINT FK_CEB65F6A61190A32');
        $this->addSql('ALTER TABLE loan_recording DROP CONSTRAINT FK_FA0017C93C20DC2A');
        $this->addSql('ALTER TABLE loan_recording DROP CONSTRAINT FK_FA0017C94A1AB7F1');
        $this->addSql('ALTER TABLE loan_recording DROP CONSTRAINT FK_FA0017C9F675F31B');
        $this->addSql('ALTER TABLE loan_recording DROP CONSTRAINT FK_FA0017C961190A32');
        $this->addSql('ALTER TABLE loan_recording_type DROP CONSTRAINT FK_1663CB8961190A32');
        $this->addSql('DROP TABLE loan');
        $this->addSql('DROP TABLE loan_category');
        $this->addSql('DROP TABLE loan_item');
        $this->addSql('DROP TABLE loan_recording');
        $this->addSql('DROP TABLE loan_recording_type');
        $this->addSql('ALTER TABLE club DROP loans_enabled');
    }
}
