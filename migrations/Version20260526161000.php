<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526161000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add associated media to web pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web_page_media (position INT NOT NULL, caption VARCHAR(255) DEFAULT NULL, keywords JSON NOT NULL, id UUID NOT NULL, web_page_id UUID NOT NULL, media_object_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B06B7D5EC4663E4 ON web_page_media (web_page_id)');
        $this->addSql('CREATE INDEX IDX_B06B7D5E64DE5A5 ON web_page_media (media_object_id)');
        $this->addSql('CREATE INDEX idx_web_page_media_position ON web_page_media (web_page_id, position)');
        $this->addSql('ALTER TABLE web_page_media ADD CONSTRAINT FK_B06B7D5EC4663E4 FOREIGN KEY (web_page_id) REFERENCES web_page (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page_media ADD CONSTRAINT FK_B06B7D5E64DE5A5 FOREIGN KEY (media_object_id) REFERENCES media_object (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_page_media DROP CONSTRAINT FK_B06B7D5EC4663E4');
        $this->addSql('ALTER TABLE web_page_media DROP CONSTRAINT FK_B06B7D5E64DE5A5');
        $this->addSql('DROP TABLE web_page_media');
    }
}
