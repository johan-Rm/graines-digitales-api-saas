<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260524165405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add media_path and media_base_url to project';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD media_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD media_base_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP media_path');
        $this->addSql('ALTER TABLE project DROP media_base_url');
    }
}
