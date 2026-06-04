<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260601210926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accommodation_media ADD representative_of_page BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE media_object DROP representative_of_page');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accommodation_media DROP representative_of_page');
        $this->addSql('ALTER TABLE media_object ADD representative_of_page BOOLEAN DEFAULT NULL');
    }
}
