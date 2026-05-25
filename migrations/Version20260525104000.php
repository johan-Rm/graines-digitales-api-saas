<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525104000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public profile fields to organization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ADD acronym VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD full_name VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD alternate_name VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD location VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD image VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE organization ADD email JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE organization ADD phone_numbers JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE organization ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ALTER email DROP DEFAULT');
        $this->addSql('ALTER TABLE organization ALTER phone_numbers DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP acronym');
        $this->addSql('ALTER TABLE organization DROP full_name');
        $this->addSql('ALTER TABLE organization DROP alternate_name');
        $this->addSql('ALTER TABLE organization DROP location');
        $this->addSql('ALTER TABLE organization DROP image');
        $this->addSql('ALTER TABLE organization DROP email');
        $this->addSql('ALTER TABLE organization DROP phone_numbers');
        $this->addSql('ALTER TABLE organization DROP description');
    }
}
