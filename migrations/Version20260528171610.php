<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528171610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accommodation_translation DROP location_description');
        $this->addSql('ALTER INDEX idx_5f81631964de5a5 RENAME TO IDX_5CF4F79864DE5A5');
        $this->addSql('ALTER INDEX idx_5f816319166d1f9c RENAME TO IDX_5CF4F798166D1F9C');
        $this->addSql('ALTER INDEX idx_5f816319b03a8386 RENAME TO IDX_5CF4F798B03A8386');
        $this->addSql('ALTER INDEX idx_5f816319896dbbde RENAME TO IDX_5CF4F798896DBBDE');
        $this->addSql('ALTER INDEX idx_b06b7d5ec4663e4 RENAME TO IDX_8DCDE4D6E4E9CD7A');
        $this->addSql('ALTER INDEX idx_b06b7d5e64de5a5 RENAME TO IDX_8DCDE4D664DE5A5');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accommodation_translation ADD location_description TEXT DEFAULT NULL');
        $this->addSql('ALTER INDEX idx_5cf4f798896dbbde RENAME TO idx_5f816319896dbbde');
        $this->addSql('ALTER INDEX idx_5cf4f798b03a8386 RENAME TO idx_5f816319b03a8386');
        $this->addSql('ALTER INDEX idx_5cf4f798166d1f9c RENAME TO idx_5f816319166d1f9c');
        $this->addSql('ALTER INDEX idx_5cf4f79864de5a5 RENAME TO idx_5f81631964de5a5');
        $this->addSql('ALTER INDEX idx_8dcde4d6e4e9cd7a RENAME TO idx_b06b7d5ec4663e4');
        $this->addSql('ALTER INDEX idx_8dcde4d664de5a5 RENAME TO idx_b06b7d5e64de5a5');
    }
}
