<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815170407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GlobalSetting widden to TEXT to support encrypted value';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_setting ALTER value TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_setting ALTER value TYPE VARCHAR(255)');
    }
}
