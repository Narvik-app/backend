<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127110848 extends AbstractMigration {
  private const string PASSKEY_GRANT = 'urn:narvik:grant:passkey';

  public function getDescription(): string {
    return 'Passkeys: add webauthn_credential & webauthn_challenge, allow the passkey grant on the front OAuth client';
  }

  public function up(Schema $schema): void {
    $this->addSql('CREATE SEQUENCE webauthn_challenge_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
    $this->addSql('CREATE SEQUENCE webauthn_credential_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
    $this->addSql('CREATE TABLE webauthn_challenge (type VARCHAR(20) NOT NULL, options TEXT NOT NULL, expire_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, user_id INT DEFAULT NULL, PRIMARY KEY (id))');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_CC50F0C4D17F50A6 ON webauthn_challenge (uuid)');
    $this->addSql('CREATE INDEX IDX_CC50F0C4A76ED395 ON webauthn_challenge (user_id)');
    $this->addSql('CREATE TABLE webauthn_credential (credential_id VARCHAR(1400) NOT NULL, record JSON NOT NULL, name VARCHAR(100) NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT NOT NULL, uuid UUID NOT NULL, user_id INT NOT NULL, PRIMARY KEY (id))');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_850123F92558A7A5 ON webauthn_credential (credential_id)');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_850123F9D17F50A6 ON webauthn_credential (uuid)');
    $this->addSql('CREATE INDEX IDX_850123F9A76ED395 ON webauthn_credential (user_id)');
    $this->addSql('ALTER TABLE webauthn_challenge ADD CONSTRAINT FK_CC50F0C4A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    $this->addSql('ALTER TABLE webauthn_credential ADD CONSTRAINT FK_850123F9A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');

    // Grants are stored space separated. An empty list already allows every grant, so it is left as is.
    // Same reconciliation as `install:oauth`, for instances that don't re-run it on upgrade.
    $this->addSql(
      "UPDATE oauth2_client SET grants = grants || ' ' || :grant WHERE identifier = 'front' AND grants IS NOT NULL AND grants <> '' AND NOT (:grant = ANY(string_to_array(grants, ' ')))",
      ['grant' => self::PASSKEY_GRANT],
    );
  }

  public function down(Schema $schema): void {
    $this->addSql(
      "UPDATE oauth2_client SET grants = array_to_string(array_remove(string_to_array(grants, ' '), :grant), ' ') WHERE identifier = 'front' AND grants IS NOT NULL",
      ['grant' => self::PASSKEY_GRANT],
    );

    $this->addSql('ALTER TABLE webauthn_challenge DROP CONSTRAINT FK_CC50F0C4A76ED395');
    $this->addSql('ALTER TABLE webauthn_credential DROP CONSTRAINT FK_850123F9A76ED395');
    $this->addSql('DROP TABLE webauthn_challenge');
    $this->addSql('DROP TABLE webauthn_credential');
    $this->addSql('DROP SEQUENCE webauthn_challenge_id_seq');
    $this->addSql('DROP SEQUENCE webauthn_credential_id_seq');
  }
}
